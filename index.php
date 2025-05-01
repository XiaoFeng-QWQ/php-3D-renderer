<?php
// 1. 首先包含必要的类文件
require_once __DIR__ . '/Renderer.php';
require_once __DIR__ . '/GameController.php';

// 2. 启动会话（应该在所有输出之前）
session_start();

// 3. 检查是否正在渲染（防止重复请求）
if (isset($_SESSION['is_rendering']) && $_SESSION['is_rendering']) {
    header('HTTP/1.1 429 Too Many Requests');
    exit;
}

// 4. 标记开始渲染
$_SESSION['is_rendering'] = true;

// 5. 初始化游戏对象
$gameWidth = 600;
$gameHeight = 300;
$renderer = new Renderer($gameWidth, $gameHeight);
$controller = new GameController($renderer);

// 6. 初始化/恢复玩家状态
if (!isset($_SESSION['player_state'])) {
    $_SESSION['player_state'] = [
        'x' => 1.5,
        'y' => 1.5,
        'angle' => 0.0,
        'fov' => M_PI / 3
    ];
}
$renderer->setPlayerState($_SESSION['player_state']);

// 7. 加载纹理（应该在设置玩家状态之后）
$renderer->setFloorTexture(__DIR__ . '/res/floor.png');
$renderer->setWallTexture(1, __DIR__ . '/res/wall.png');

// 8. 处理输入
if (isset($_GET['keys'])) {
    $keys = json_decode($_GET['keys'], true);
    foreach ($keys as $key => $pressed) {
        $controller->setKeyState($key, $pressed);
    }
}

if (isset($_GET['mouseX'])) {
    $controller->setMousePosition((int)$_GET['mouseX']);
}

// 9. 更新游戏状态
$controller->handleInput();

// 10. 渲染游戏
$renderer->render();
$imageData = $renderer->outputImage();

// 11. 保存游戏状态
$_SESSION['player_state'] = $controller->getPlayerState();

// 12. 标记渲染完成
$_SESSION['is_rendering'] = false;

// 13. 处理输出
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'image' => $imageData,
        'state' => $renderer->getPlayerState()
    ]);
    exit;
}

// 14. 如果不是AJAX请求，继续输出HTML
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raycaster Game</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
        }

        #gameContainer {
            position: relative;
            width: 100vw;
            height: 100vh;
        }

        #gameCanvas {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: block;
            background-color: #000;
        }

        #controls {
            position: fixed;
            bottom: 0;
            left: 0;
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 5px;
        }

        #loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 24px;
            display: none;
        }

        #fpsCounter {
            z-index: 10;
            position: absolute;
            top: 10px;
            right: 10px;
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 5px;
        }

        #playerState {
            z-index: 10;
            position: absolute;
            top: 10px;
            left: 10px;
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 5px;
        }

        #fpsChartContainer {
            position: absolute;
            bottom: 50px;
            right: 10px;
            width: 300px;
            height: 150px;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 5px;
            z-index: 10;
        }

        #fpsChart {
            width: 100%;
            height: 100%;
        }

        .chart-label {
            position: absolute;
            color: white;
            font-size: 10px;
        }

        #fpsMin {
            bottom: 5px;
            right: 5px;
        }

        #fpsMax {
            top: 5px;
            right: 5px;
        }

        #fpsAvg {
            bottom: 50%;
            right: 5px;
            transform: translateY(50%);
        }
    </style>
</head>

<body>
    <div id="gameContainer">
        <canvas id="gameCanvas" width="<?= $gameWidth ?>" height="<?= $gameHeight ?>"></canvas>
        <div id="controls">WASD/Arrow Keys: Move | Q/E: Rotate | Z/X: Adjust FOV</div>
        <div id="loading">Loading...</div>
        <div id="fpsCounter">FPS: 0</div>
        <div id="playerState">x, y, z</div>
        <div id="fpsChartContainer">
            <canvas id="fpsChart"></canvas>
            <div id="fpsMin" class="chart-label">Min: 0</div>
            <div id="fpsMax" class="chart-label">Max: 0</div>
            <div id="fpsAvg" class="chart-label">Avg: 0</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const canvas = $('#gameCanvas')[0];
            const ctx = canvas.getContext('2d');
            const loadingElement = $('#loading');
            const fpsCounter = $('#fpsCounter');

            // FPS 图表相关变量
            const fpsChartCanvas = $('#fpsChart')[0];
            const fpsChartCtx = fpsChartCanvas.getContext('2d');
            fpsChartCanvas.width = 300;
            fpsChartCanvas.height = 150;

            // FPS 数据记录
            const fpsHistory = [];
            const maxHistoryLength = 100; // 记录最近100帧的FPS
            let minFps = Infinity;
            let maxFps = 0;
            let totalFps = 0;
            let frameCount = 0;

            // 游戏状态
            const keys = {
                'w': false,
                'a': false,
                's': false,
                'd': false,
                'ArrowUp': false,
                'ArrowDown': false,
                'ArrowLeft': false,
                'ArrowRight': false,
                'q': false,
                'e': false,
                'z': false,
                'x': false
            };

            let mouseX = 0;
            let lastMouseX = 0;
            let isMouseLocked = false;
            let lastFrameTime = 0;
            let fps = 0;

            // 绘制FPS图表
            const drawFpsChart = () => {
                // 清除画布
                fpsChartCtx.clearRect(0, 0, fpsChartCanvas.width, fpsChartCanvas.height);

                // 绘制背景网格
                fpsChartCtx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
                fpsChartCtx.beginPath();
                for (let i = 0; i <= 10; i++) {
                    const y = i * (fpsChartCanvas.height / 10);
                    fpsChartCtx.moveTo(0, y);
                    fpsChartCtx.lineTo(fpsChartCanvas.width, y);
                }
                fpsChartCtx.stroke();

                // 如果没有数据则不绘制
                if (fpsHistory.length === 0) return;

                // 计算当前最小、最大和平均FPS
                const currentMin = Math.min(...fpsHistory);
                const currentMax = Math.max(...fpsHistory);
                const currentAvg = Math.round(totalFps / frameCount);

                // 更新显示
                $('#fpsMin').text(`Min: ${currentMin}`);
                $('#fpsMax').text(`Max: ${currentMax}`);
                $('#fpsAvg').text(`Avg: ${currentAvg}`);

                // 绘制FPS曲线
                fpsChartCtx.beginPath();
                fpsChartCtx.strokeStyle = '#4CAF50';
                fpsChartCtx.lineWidth = 2;

                const xStep = fpsChartCanvas.width / (maxHistoryLength - 1);
                const scaleY = fpsChartCanvas.height / (currentMax * 1.2); // 留出20%的顶部空间

                for (let i = 0; i < fpsHistory.length; i++) {
                    const x = i * xStep;
                    const y = fpsChartCanvas.height - (fpsHistory[i] * scaleY);

                    if (i === 0) {
                        fpsChartCtx.moveTo(x, y);
                    } else {
                        fpsChartCtx.lineTo(x, y);
                    }
                }

                fpsChartCtx.stroke();

                // 绘制当前FPS标记
                const lastFps = fpsHistory[fpsHistory.length - 1];
                const lastX = (fpsHistory.length - 1) * xStep;
                const lastY = fpsChartCanvas.height - (lastFps * scaleY);

                fpsChartCtx.beginPath();
                fpsChartCtx.arc(lastX, lastY, 3, 0, Math.PI * 2);
                fpsChartCtx.fillStyle = '#FF5722';
                fpsChartCtx.fill();

                // 绘制FPS数值
                fpsChartCtx.fillStyle = 'white';
                fpsChartCtx.font = '10px Arial';
                fpsChartCtx.fillText(lastFps, lastX + 5, lastY - 5);
            };

            // 更新FPS数据
            const updateFpsData = (currentFps) => {
                fpsHistory.push(currentFps);
                if (fpsHistory.length > maxHistoryLength) {
                    fpsHistory.shift();
                }

                // 更新统计
                if (currentFps < minFps) minFps = currentFps;
                if (currentFps > maxFps) maxFps = currentFps;
                totalFps += currentFps;
                frameCount++;

                // 绘制图表
                drawFpsChart();
            };

            // 锁定鼠标到画布
            const lockMouse = () => {
                canvas.requestPointerLock = canvas.requestPointerLock ||
                    canvas.mozRequestPointerLock ||
                    canvas.webkitRequestPointerLock;
                canvas.requestPointerLock();
            };

            // 初始化事件监听
            const initEventListeners = () => {
                // 键盘事件
                $(document).on('keydown keyup', (e) => {
                    if (keys.hasOwnProperty(e.key)) {
                        keys[e.key] = e.type === 'keydown';
                        e.preventDefault();
                    }
                });

                // 鼠标移动事件
                $(document).on('mousemove', (e) => {
                    if (isMouseLocked) {
                        mouseX += e.movementX || e.mozMovementX || e.webkitMovementX || 0;
                    }
                });

                // 鼠标锁定/解锁事件
                const events = [
                    'pointerlockchange',
                    'mozpointerlockchange',
                    'webkitpointerlockchange'
                ];
                events.forEach(evt => {
                    document.addEventListener(evt, () => {
                        isMouseLocked = document.pointerLockElement === canvas ||
                            document.mozPointerLockElement === canvas ||
                            document.webkitPointerLockElement === canvas;
                    });
                });

                // 点击画布锁定鼠标
                $(canvas).on('click', () => {
                    if (!isMouseLocked) lockMouse();
                });
            };

            // 发送请求到服务器获取渲染图像
            const fetchGameFrame = async () => {
                try {
                    const params = {
                        keys: JSON.stringify(keys),
                        ajax: 'true'
                    };

                    if (isMouseLocked && mouseX !== lastMouseX) {
                        params.mouseX = mouseX - lastMouseX;
                        lastMouseX = mouseX;
                    }

                    const response = await $.ajax({
                        url: '?' + $.param(params),
                        dataType: 'json',
                        error: (xhr) => {
                            if (xhr.status === 429) {
                                console.log('Too many requests, skipping frame');
                            } else {
                                console.error('HTTP error! status:', xhr.status);
                            }
                        }
                    });

                    if (response.state) {
                        $('#playerState').html(
                            `x: ${response.state.x.toFixed(2)}, y: ${response.state.y.toFixed(2)}, angle: ${response.state.angle.toFixed(2)}
                            <br>Fov: ${response.state.fov.toFixed(2)}, Width: <?= $gameWidth ?>, Height: <?= $gameHeight ?>`
                        );
                    }

                    return response?.image || null;
                } catch (error) {
                    console.error('Error fetching game frame:', error);
                    return null;
                } finally {
                    loadingElement.hide();
                }
            };

            // 绘制图像到画布
            const drawFrame = (imageData) => {
                if (!imageData) return;
                const img = new Image();
                img.onload = function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0);
                };
                img.src = 'data:image/png;base64,' + imageData;
            };

            // 游戏循环
            const gameLoop = async () => {
                const now = performance.now();
                const delta = now - lastFrameTime;
                lastFrameTime = now;

                // 计算FPS
                fps = Math.round(1000 / (delta || 1));
                fpsCounter.text(`FPS: ${fps}`);
                updateFpsData(fps);

                try {
                    const imageData = await fetchGameFrame();
                    drawFrame(imageData);
                    loadingElement.hide();
                } catch (error) {
                    console.error('Error in game loop:', error);
                } finally {
                    requestAnimationFrame(gameLoop);
                }
            };

            // 初始化游戏
            const initGame = () => {
                loadingElement.show();
                initEventListeners();
                lastFrameTime = performance.now();
                gameLoop();
            };

            // 启动游戏
            initGame();
        });
    </script>
</body>

</html>