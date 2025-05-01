<?php

/**
 * 3D渲染器类，使用光线投射算法实现伪3D效果
 */
class Renderer
{
    private $width;          // 画布宽度
    private $height;         // 画布高度
    private $image;          // GD图像资源
    private $textures = [];  // 纹理数组
    private $skyboxTexture;      // 天空盒纹理
    private $floorTexture;       // 地板纹理
    private $ceilingTexture;     // 天花板纹理
    private $map = [];       // 地图数据(二维数组)

    // 玩家位置和视角
    public $playerX = 1.5;   // 玩家X坐标
    public $playerY = 1.5;   // 玩家Y坐标
    public $playerAngle = 0.0; // 玩家视角角度(弧度)
    private $fov = M_PI / 3; // 视野范围(60度)

    /**
     * 构造函数
     * @param int $width 画布宽度
     * @param int $height 画布高度
     */
    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->image = imagecreatetruecolor($width, $height);

        // 初始化简单地图 (1=墙, 0=空地)
        $this->map = [
            [1, 1, 1, 1, 1],
            [0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0]
        ];

        // 生成默认纹理
        $this->generateDefaultTextures();
        $this->generateDefaultSkybox();
        $this->generateDefaultFloorAndCeiling();
    }

    /**
     * 设置自定义墙墙壁纹理
     * @param int $textureId 纹理ID (1-4)
     * @param string $imagePath 图片路径
     * @return bool 是否成功
     */
    public function setWallTexture(int $textureId, string $imagePath): bool
    {
        if ($textureId < 1 || $textureId > 4) {
            return false;
        }

        if (!file_exists($imagePath)) {
            return false;
        }

        $texture = @imagecreatefromstring(file_get_contents($imagePath));
        if ($texture === false) {
            return false;
        }

        // 替换现有纹理
        if (isset($this->textures[$textureId])) {
            imagedestroy($this->textures[$textureId]);
        }
        $this->textures[$textureId] = $texture;
        return true;
    }

    /**
     * 设置自定义天空盒纹理
     * @param string $imagePath 图片路径
     * @return bool 是否成功
     */
    public function setSkyboxTexture(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        $texture = @imagecreatefromstring(file_get_contents($imagePath));
        if ($texture === false) {
            return false;
        }

        if ($this->skyboxTexture instanceof GdImage) {
            imagedestroy($this->skyboxTexture);
        }
        $this->skyboxTexture = $texture;
        return true;
    }

    /**
     * 设置自定义地板纹理
     * @param string $imagePath 图片路径
     * @return bool 是否成功
     */
    public function setFloorTexture(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        $texture = @imagecreatefromstring(file_get_contents($imagePath));
        if ($texture === false) {
            return false;
        }

        if ($this->floorTexture instanceof GdImage) {
            imagedestroy($this->floorTexture);
        }
        $this->floorTexture = $texture;
        return true;
    }

    /**
     * 设置自定义天花板纹理
     * @param string $imagePath 图片路径
     * @return bool 是否成功
     */
    public function setCeilingTexture(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        $texture = @imagecreatefromstring(file_get_contents($imagePath));
        if ($texture === false) {
            return false;
        }

        if ($this->ceilingTexture instanceof GdImage) {
            imagedestroy($this->ceilingTexture);
        }
        $this->ceilingTexture = $texture;
        return true;
    }

    /**
     * 生成默认天空盒纹理
     */
    private function generateDefaultSkybox(): void
    {
        $texSize = 256;
        $this->skyboxTexture = imagecreatetruecolor($texSize, $texSize);

        // 创建渐变天空效果
        for ($y = 0; $y < $texSize; $y++) {
            // 从上到下渐变 (深蓝到浅蓝)
            $blueIntensity = (int)(255 - ($y / $texSize) * 155);
            $color = imagecolorallocate($this->skyboxTexture, 100, 150, $blueIntensity);
            imageline($this->skyboxTexture, 0, $y, $texSize - 1, $y, $color);
        }

        // 添加一些云朵效果
        $cloudColor = imagecolorallocatealpha($this->skyboxTexture, 255, 255, 255, 60);
        for ($i = 0; $i < 10; $i++) {
            $x = rand(0, (int)($texSize - 50));
            $y = rand(0, (int)($texSize / 3));
            $w = rand(30, 80);
            $h = rand(10, 20);
            imagefilledellipse($this->skyboxTexture, $x, $y, $w, $h, $cloudColor);
        }
    }

    /**
     * 生成默认地板和天花板纹理
     */
    private function generateDefaultFloorAndCeiling(): void
    {
        $texSize = 64;

        // 地板纹理 (深色格子)
        $this->floorTexture = imagecreatetruecolor($texSize, $texSize);
        $darkColor = imagecolorallocate($this->floorTexture, 80, 80, 80);
        $lightColor = imagecolorallocate($this->floorTexture, 60, 60, 60);

        for ($y = 0; $y < $texSize; $y++) {
            for ($x = 0; $x < $texSize; $x++) {
                $useDark = ((int)($x / 8) + (int)($y / 8)) % 2;
                imagesetpixel($this->floorTexture, $x, $y, $useDark ? $darkColor : $lightColor);
            }
        }

        // 天花板纹理 (浅色)
        $this->ceilingTexture = imagecreatetruecolor($texSize, $texSize);
        $ceilingColor = imagecolorallocate($this->ceilingTexture, 200, 200, 210);
        imagefill($this->ceilingTexture, 0, 0, $ceilingColor);
    }

    /**
     * 生成默认纹理(棋盘格样式)
     */
    private function generateDefaultTextures(): void
    {
        // 创建4种不同颜色的默认墙面纹理
        $colorSets = [
            [255, 0, 0],   // 红
            [0, 255, 0],   // 绿
            [0, 0, 255],   // 蓝
            [255, 255, 0]  // 黄
        ];

        $texSize = 64;
        foreach ($colorSets as $index => $colorData) {
            $texture = imagecreatetruecolor($texSize, $texSize);

            $r = max(0, min(255, (int)$colorData[0]));
            $g = max(0, min(255, (int)$colorData[1]));
            $b = max(0, min(255, (int)$colorData[2]));

            $color = imagecolorallocate($texture, $r, $g, $b);
            $darkColor = imagecolorallocate(
                $texture,
                (int)($r * 0.7),
                (int)($g * 0.7),
                (int)($b * 0.7)
            );

            // 创建棋盘格纹理
            for ($y = 0; $y < $texSize; $y++) {
                for ($x = 0; $x < $texSize; $x++) {
                    $useDark = ((int)($x / 8) + (int)($y / 8)) % 2;
                    imagesetpixel($texture, $x, $y, $useDark ? $darkColor : $color);
                }
            }

            $this->textures[$index + 1] = $texture;
        }
    }

    /**
     * 渲染整个场景
     */
    public function render(): void
    {
        // 清空画布
        $black = imagecolorallocate($this->image, 0, 0, 0);
        imagefill($this->image, 0, 0, $black);

        // 渲染3D视图
        $this->render3DView();
    }

    /**
     * 渲染3D视图(使用光线投射算法)
     */
    private function render3DView(): void
    {
        $halfHeight = $this->height / 2;

        // 首先渲染天空盒
        $this->renderSkybox();

        // 然后渲染地板和天花板
        $this->renderFloorAndCeiling();

        // 最后渲染墙壁
        // 对屏幕的每一列进行光线投射
        for ($x = 0; $x < $this->width; $x++) {
            // 计算当前射线角度 (从视野左侧到右侧)
            $rayAngle = $this->playerAngle - $this->fov / 2 + $this->fov * $x / $this->width;

            // 初始化射线
            $rayX = $this->playerX;
            $rayY = $this->playerY;

            // 射线方向单位向量
            $dirX = cos($rayAngle);
            $dirY = sin($rayAngle);

            // 避免除以零
            if ($dirX == 0) {
                $dirX = 1e-6;
            }
            if ($dirY == 0) {
                $dirY = 1e-6;
            }

            // 射线步进增量
            $deltaDistX = abs(1 / $dirX);
            $deltaDistY = abs(1 / $dirY);

            // 确定初始步进方向和距离
            $mapX = floor($rayX);
            $mapY = floor($rayY);

            $sideDistX = 0;
            $sideDistY = 0;

            $stepX = 0;
            $stepY = 0;

            $hit = 0; // 是否击中墙面
            $side = 0; // 0=垂直面, 1=水平面

            // 计算初始步进方向
            if ($dirX < 0) {
                $stepX = -1;
                $sideDistX = ($rayX - $mapX) * $deltaDistX;
            } else {
                $stepX = 1;
                $sideDistX = ($mapX + 1.0 - $rayX) * $deltaDistX;
            }

            if ($dirY < 0) {
                $stepY = -1;
                $sideDistY = ($rayY - $mapY) * $deltaDistY;
            } else {
                $stepY = 1;
                $sideDistY = ($mapY + 1.0 - $rayY) * $deltaDistY;
            }

            // 执行DDA算法 (数字微分分析)
            while ($hit == 0) {
                // 选择下一个要检查的网格
                if ($sideDistX < $sideDistY) {
                    $sideDistX += $deltaDistX;
                    $mapX += $stepX;
                    $side = 0;
                } else {
                    $sideDistY += $deltaDistY;
                    $mapY += $stepY;
                    $side = 1;
                }

                // 检查是否击中墙面
                if ($mapX < 0 || $mapY < 0 || $mapX >= count($this->map) || $mapY >= count($this->map[0])) {
                    break; // 超出地图边界
                }

                if ($this->map[$mapX][$mapY] > 0) {
                    $hit = $this->map[$mapX][$mapY]; // 返回墙面类型
                }
            }

            // 计算击中点的距离和高度
            if ($hit > 0) {
                // 计算垂直距离(避免鱼眼效果)
                if ($side == 0) {
                    $perpWallDist = ($mapX - $rayX + (1 - $stepX) / 2) / $dirX;
                } else {
                    $perpWallDist = ($mapY - $rayY + (1 - $stepY) / 2) / $dirY;
                }

                // 计算墙面高度(基于距离)
                $lineHeight = (int)($this->height / $perpWallDist);

                // 计算绘制范围(在屏幕上的Y坐标)
                $drawStart = -$lineHeight / 2 + $halfHeight;
                if ($drawStart < 0) $drawStart = 0;

                $drawEnd = $lineHeight / 2 + $halfHeight;
                if ($drawEnd >= $this->height) $drawEnd = $this->height - 1;

                // 纹理映射
                $texId = $hit;
                $texWidth = imagesx($this->textures[$texId]);
                $texHeight = imagesy($this->textures[$texId]);

                // 计算墙面击中点的精确位置
                $wallX = 0;
                if ($side == 0) {
                    $wallX = $rayY + $perpWallDist * $dirY;
                } else {
                    $wallX = $rayX + $perpWallDist * $dirX;
                }
                $wallX -= floor($wallX);

                // 计算纹理X坐标
                $texX = (int)($wallX * $texWidth);
                if (($side == 0 && $dirX > 0) || ($side == 1 && $dirY < 0)) {
                    $texX = $texWidth - $texX - 1;
                }

                // 绘制墙面切片
                for ($y = (int)floor($drawStart); $y < (int)floor($drawEnd); $y++) {
                    // 计算纹理Y坐标
                    $texY = (int)(($y - $halfHeight + $lineHeight / 2) * $texHeight / $lineHeight);
                    // 确保纹理坐标在有效范围内
                    $texX = max(0, min(imagesx($this->textures[$texId]) - 1, $texX));
                    $texY = max(0, min(imagesy($this->textures[$texId]) - 1, $texY));
                    // 获取纹理颜色并绘制像素
                    $color = imagecolorat($this->textures[$texId], $texX, $texY);
                    imagesetpixel($this->image, $x, $y, $color);
                }
            }
        }
    }

    /**
     * 渲染天空盒
     */
    private function renderSkybox(): void
    {
        $skyWidth = imagesx($this->skyboxTexture);
        $skyHeight = imagesy($this->skyboxTexture);

        // 计算天空在屏幕上的显示高度 (上半部分)
        $skyHeightOnScreen = $this->height / 2;

        // 根据玩家视角角度确定天空盒采样位置
        $skyOffset = (int)(($this->playerAngle / (2 * M_PI)) * $skyWidth);

        // 渲染天空
        for ($y = 0; $y < $skyHeightOnScreen; $y++) {
            // 计算纹理Y坐标 (从顶部开始采样)
            $texY = (int)(($y / $skyHeightOnScreen) * $skyHeight);

            for ($x = 0; $x < $this->width; $x++) {
                // 计算纹理X坐标 (考虑视角偏移)
                $texX = ($x + $skyOffset) % $skyWidth;

                $color = imagecolorat($this->skyboxTexture, $texX, $texY);
                imagesetpixel($this->image, $x, $y, $color);
            }
        }
    }

    /**
     * 渲染地板和天花板
     */
    /**
     * 渲染地板和天花板
     */
    private function renderFloorAndCeiling(): void
    {
        $halfHeight = (int)($this->height / 2);

        // 获取贴图尺寸
        $floorTexWidth = imagesx($this->floorTexture);
        $floorTexHeight = imagesy($this->floorTexture);
        $ceilingTexWidth = imagesx($this->ceilingTexture);
        $ceilingTexHeight = imagesy($this->ceilingTexture);

        for ($y = $halfHeight; $y < $this->height; $y++) {
            $verticalPos = ($y - $halfHeight) / max(1, $halfHeight);
            $rowDistance = 1.0 / max(0.001, $verticalPos);

            $floorStepX = $rowDistance * cos($this->playerAngle + $this->fov / 2) / max(1, $this->width);
            $floorStepY = $rowDistance * sin($this->playerAngle + $this->fov / 2) / max(1, $this->width);

            $floorX = $this->playerX + $rowDistance * cos($this->playerAngle - $this->fov / 2);
            $floorY = $this->playerY + $rowDistance * sin($this->playerAngle - $this->fov / 2);

            for ($x = 0; $x < $this->width; $x++) {
                $cellX = (int)floor($floorX);
                $cellY = (int)floor($floorY);

                // 计算 floor 贴图坐标
                $texX = ((int)floor($floorTexWidth * ($floorX - $cellX)) % $floorTexWidth + $floorTexWidth) % $floorTexWidth;
                $texY = ((int)floor($floorTexHeight * ($floorY - $cellY)) % $floorTexHeight + $floorTexHeight) % $floorTexHeight;

                // 限制范围
                $texX = max(0, min($floorTexWidth - 1, $texX));
                $texY = max(0, min($floorTexHeight - 1, $texY));

                // 地板像素
                if ($texX >= 0 && $texX < $floorTexWidth && $texY >= 0 && $texY < $floorTexHeight) {
                    $floorColor = imagecolorat($this->floorTexture, $texX, $texY);
                    imagesetpixel($this->image, $x, $y, $floorColor);
                }

                // 天花板像素
                $ceilingY = $this->height - $y - 1;
                if ($ceilingY >= 0 && $ceilingY < $this->height) {
                    $cTexX = $texX % $ceilingTexWidth;
                    $cTexY = $texY % $ceilingTexHeight;
                    if ($cTexX >= 0 && $cTexX < $ceilingTexWidth && $cTexY >= 0 && $cTexY < $ceilingTexHeight) {
                        $ceilingColor = imagecolorat($this->ceilingTexture, $cTexX, $cTexY);
                        imagesetpixel($this->image, $x, $ceilingY, $ceilingColor);
                    }
                }

                $floorX += $floorStepX;
                $floorY += $floorStepY;
            }
        }
    }

    /**
     * 玩家前进/后退
     * @param float $distance 移动距离(正数前进，负数后退)
     */
    public function movePlayer(float $distance): void
    {
        $moveX = cos($this->playerAngle) * $distance;
        $moveY = sin($this->playerAngle) * $distance;

        $newX = $this->playerX + $moveX;
        $newY = $this->playerY + $moveY;

        // 分别检查X和Y轴的碰撞
        if (!$this->isWall($newX, $this->playerY)) {
            $this->playerX = $newX;
        }
        if (!$this->isWall($this->playerX, $newY)) {
            $this->playerY = $newY;
        }
    }

    /**
     * 玩家平移(左右移动)
     * @param float $distance 移动距离(正数右移，负数左移)
     */
    public function strafePlayer(float $distance): void
    {
        // 计算垂直于当前角度的方向（左右平移）
        $strafeAngle = $this->playerAngle - M_PI_2; // 向左90度
        $moveX = cos($strafeAngle) * $distance;
        $moveY = sin($strafeAngle) * $distance;

        $newX = $this->playerX + $moveX;
        $newY = $this->playerY + $moveY;

        if (!$this->isWall($newX, $this->playerY)) {
            $this->playerX = $newX;
        }
        if (!$this->isWall($this->playerX, $newY)) {
            $this->playerY = $newY;
        }
    }

    /**
     * 旋转玩家视角
     * @param float $angle 旋转角度(弧度)
     */
    public function rotatePlayer(float $angle): void
    {
        $this->playerAngle += $angle;
        // 规范化角度到0-2π范围
        $this->playerAngle = fmod($this->playerAngle, 2 * M_PI);
        if ($this->playerAngle < 0) {
            $this->playerAngle += 2 * M_PI;
        }
    }

    /**
     * 获取玩家状态
     * @return array 包含玩家位置和角度的数组
     */
    public function getPlayerState(): array
    {
        return [
            'x' => $this->playerX,
            'y' => $this->playerY,
            'angle' => $this->playerAngle,
            'fov' => $this->fov
        ];
    }

    /**
     * 设置玩家状态
     * @param array $state 包含玩家位置和角度的数组
     */
    public function setPlayerState(array $state): void
    {
        $this->playerX = $state['x'] ?? $this->playerX;
        $this->playerY = $state['y'] ?? $this->playerY;
        $this->playerAngle = $state['angle'] ?? $this->playerAngle;
        $this->fov = $state['fov'] ?? $this->fov;
    }

    /**
     * 获取地图数据
     * @return array 地图二维数组
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * 检查指定位置是否是墙
     * @param float $x X坐标
     * @param float $y Y坐标
     * @return bool 是否是墙
     */
    private function isWall(float $x, float $y): bool
    {
        $mapX = floor($x);
        $mapY = floor($y);

        if ($mapX < 0 || $mapY < 0 || $mapX >= count($this->map) || $mapY >= count($this->map[0])) {
            return true;
        }

        return $this->map[$mapX][$mapY] > 0;
    }

    /**
     * 输出图像为base64编码的PNG
     * @return string base64编码的图像数据
     */
    public function outputImage(): string
    {
        ob_start();
        imagepng($this->image);
        imagedestroy($this->image);
        return base64_encode(ob_get_clean());
    }

    /**
     * 析构函数，释放资源
     */
    public function __destruct()
    {
        // 释放所有纹理资源
        foreach ($this->textures as $texture) {
            if ($texture instanceof \GdImage) {
                imagedestroy($texture);
            }
        }

        // 释放新增的纹理资源
        if ($this->skyboxTexture instanceof GdImage) {
            imagedestroy($this->skyboxTexture);
        }

        if ($this->floorTexture instanceof GdImage) {
            imagedestroy($this->floorTexture);
        }

        if ($this->ceilingTexture instanceof GdImage) {
            imagedestroy($this->ceilingTexture);
        }

        if ($this->image instanceof GdImage) {
            imagedestroy($this->image);
        }
    }
}
