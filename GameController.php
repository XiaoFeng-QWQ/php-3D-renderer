<?php

/**
 * 游戏控制器类，负责处理玩家输入和控制游戏逻辑
 */
class GameController
{
    private $renderer;           // 渲染器实例
    private $moveSpeed = 0.1;    // 移动速度
    private $rotationSpeed = 0.05; // 旋转速度
    private $mouseSensitivity = 0.002; // 鼠标灵敏度
    private $keys = [];          // 键盘按键状态数组
    private $lastMouseX = null;  // 上一次鼠标X坐标
    private $isMouseLocked = false; // 鼠标是否锁定(用于视角控制)
    private $fovChangeSpeed = 0.01; // 视野变化速度

    /**
     * 构造函数
     * @param Renderer $renderer 渲染器实例
     */
    public function __construct($renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * 获取玩家状态
     * @return array 包含玩家位置和视角的数组
     */
    public function getPlayerState(): array
    {
        return $this->renderer->getPlayerState();
    }

    /**
     * 设置玩家状态
     * @param array $state 包含玩家位置和视角的数组
     */
    public function setPlayerState(array $state): void
    {
        $this->renderer->setPlayerState($state);
    }

    /**
     * 处理所有输入(键盘和鼠标)
     */
    public function handleInput(): void
    {
        $this->handleKeyboard();
        $this->handleMouse();
    }

    /**
     * 处理键盘输入
     */
    private function handleKeyboard(): void
    {
        // 前进/后退
        if ($this->isKeyPressed('ArrowUp') || $this->isKeyPressed('w')) {
            $this->renderer->movePlayer($this->moveSpeed);
        }
        if ($this->isKeyPressed('ArrowDown') || $this->isKeyPressed('s')) {
            $this->renderer->movePlayer(-$this->moveSpeed);
        }

        // 左右平移（使用新的strafePlayer方法）
        if ($this->isKeyPressed('ArrowLeft') || $this->isKeyPressed('a')) {
            $this->renderer->strafePlayer($this->moveSpeed);
        }
        if ($this->isKeyPressed('ArrowRight') || $this->isKeyPressed('d')) {
            $this->renderer->strafePlayer(-$this->moveSpeed);
        }

        // 左右旋转
        if ($this->isKeyPressed('q')) {
            $this->renderer->rotatePlayer(-$this->rotationSpeed);
        }
        if ($this->isKeyPressed('e')) {
            $this->renderer->rotatePlayer($this->rotationSpeed);
        }

        // 调整FOV(视野范围)
        if ($this->isKeyPressed('z')) {
            $state = $this->renderer->getPlayerState();
            $state['fov'] = max(0.1, $state['fov'] - $this->fovChangeSpeed);
            $this->renderer->setPlayerState($state);
        }
        if ($this->isKeyPressed('x')) {
            $state = $this->renderer->getPlayerState();
            $state['fov'] = min(M_PI / 2, $state['fov'] + $this->fovChangeSpeed);
            $this->renderer->setPlayerState($state);
        }
    }

    /**
     * 处理鼠标输入
     */
    private function handleMouse(): void
    {
        // 如果鼠标未锁定则忽略
        if (!$this->isMouseLocked) return;

        // 鼠标移动控制视角
        if ($this->lastMouseX !== null && isset($_SERVER['HTTP_X_MOUSE_POS'])) {
            $currentMouseX = (int)$_SERVER['HTTP_X_MOUSE_POS'];
            $deltaX = $currentMouseX - $this->lastMouseX; // 计算鼠标移动距离
            $this->renderer->rotatePlayer($deltaX * $this->mouseSensitivity);
        }
    }

    /**
     * 检查按键是否被按下
     * @param string $key 按键名称
     * @return bool 是否按下
     */
    public function isKeyPressed(string $key): bool
    {
        return $this->keys[$key] ?? false;
    }

    /**
     * 设置按键状态
     * @param string $key 按键名称
     * @param bool $pressed 是否按下
     */
    public function setKeyState(string $key, bool $pressed): void
    {
        $this->keys[$key] = $pressed;
    }

    /**
     * 设置鼠标位置
     * @param int $x 鼠标X坐标
     */
    public function setMousePosition(int $x): void
    {
        $this->lastMouseX = $x;
    }

    /**
     * 设置鼠标锁定状态
     * @param bool $locked 是否锁定
     */
    public function setMouseLocked(bool $locked): void
    {
        $this->isMouseLocked = $locked;
    }

    /**
     * 获取鼠标锁定状态
     * @return bool 是否锁定
     */
    public function isMouseLocked(): bool
    {
        return $this->isMouseLocked;
    }

    /**
     * 获取移动速度
     * @return float 移动速度
     */
    public function getMoveSpeed(): float
    {
        return $this->moveSpeed;
    }

    /**
     * 设置移动速度
     * @param float $speed 新的移动速度
     */
    public function setMoveSpeed(float $speed): void
    {
        $this->moveSpeed = $speed;
    }
}
