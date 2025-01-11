<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

class GameState {
    public $player1Y = 250;
    public $player2Y = 250;
    public $ballX = 400;
    public $ballY = 300;
    public $ballSpeedX = 7;
    public $ballSpeedY = 7;
    public $player1Score = 0;
    public $player2Score = 0;
    public $gameStarted = false;
    public $running = false;
    public $winner = null;
    public $canvasWidth = 800;
    public $canvasHeight = 600;
    public $paddleHeight = 100;
    public $paddleWidth = 10;
    public $ballSize = 10;
    public $obstacles = [];
    public $maxScore = 10;

    public function __construct() {
        $this->resetGame();
    }

    public function resetGame() {
        $this->player1Y = 250;
        $this->player2Y = 250;
        $this->ballX = 400;
        $this->ballY = 300;
        $this->ballSpeedX = 7;
        $this->ballSpeedY = 7;
        $this->player1Score = 0;
        $this->player2Score = 0;
        $this->gameStarted = false;
        $this->running = false;
        $this->winner = null;
        $this->obstacles = $this->generateObstacles();
    }

    public function generateObstacles() {
        $obstacles = [];
        for ($i = 0; $i < 2; $i++) {
            $obstacles[] = [
                'x' => rand(100, 700),
                'y' => rand(100, 500),
                'size' => 30
            ];
        }
        return $obstacles;
    }

    public function toArray() {
        return [
            'player1Y' => $this->player1Y,
            'player2Y' => $this->player2Y,
            'ballX' => $this->ballX,
            'ballY' => $this->ballY,
            'player1Score' => $this->player1Score,
            'player2Score' => $this->player2Score,
            'obstacles' => $this->obstacles,
            'game_started' => $this->gameStarted,
            'running' => $this->running,
            'winner' => $this->winner
        ];
    }

    public function startGame() {
        $this->gameStarted = true;
        $this->running = true;
        $this->winner = null;
        $this->player1Score = 0;
        $this->player2Y = 250;
        $this->player1Y = 250;
        $this->obstacles = $this->generateObstacles();
        $this->resetBall();
    }

    public function checkWinner() {
        if ($this->player1Score >= $this->maxScore) {
            $this->winner = 1;
            $this->running = false;
        } elseif ($this->player2Score >= $this->maxScore) {
            $this->winner = 2;
            $this->running = false;
        }
    }

    public function resetBall() {
        $this->ballX = $this->canvasWidth / 2;
        $this->ballY = $this->canvasHeight / 2;
        $baseSpeed = 7;
        $this->ballSpeedX = (rand(0, 1) == 0 ? -$baseSpeed : $baseSpeed);
        $this->ballSpeedY = rand(-$baseSpeed * 100, $baseSpeed * 100) / 100;
    }

    public function update() {
        if (!$this->running) {
            return;
        }

        $nextX = $this->ballX + $this->ballSpeedX;
        $nextY = $this->ballY + $this->ballSpeedY;

        if ($nextY <= 0 || $nextY >= $this->canvasHeight) {
            $this->ballSpeedY *= -1;
            $nextY = max(0, min($nextY, $this->canvasHeight));
        }

        if ($nextX <= 0) {
            if ($this->player1Y <= $this->ballY && $this->ballY <= $this->player1Y + $this->paddleHeight) {
                $relativeIntersect = ($this->ballY - ($this->player1Y + $this->paddleHeight/2)) / ($this->paddleHeight/2);
                $bounceAngle = $relativeIntersect * 0.75;
                $speed = sqrt(pow($this->ballSpeedX, 2) + pow($this->ballSpeedY, 2));
                $this->ballSpeedX = abs($speed * 1.1);
                $this->ballSpeedY = $speed * -$bounceAngle;
            } else {
                $this->player2Score++;
                $this->checkWinner();
                $this->ballSpeedX *= -1;
                $nextX = $this->ballSize;
                $this->ballSpeedY = rand(-700, 700) / 100;
            }
        } elseif ($nextX >= $this->canvasWidth) {
            if ($this->player2Y <= $this->ballY && $this->ballY <= $this->player2Y + $this->paddleHeight) {
                $relativeIntersect = ($this->ballY - ($this->player2Y + $this->paddleHeight/2)) / ($this->paddleHeight/2);
                $bounceAngle = $relativeIntersect * 0.75;
                $speed = sqrt(pow($this->ballSpeedX, 2) + pow($this->ballSpeedY, 2));
                $this->ballSpeedX = -abs($speed * 1.1);
                $this->ballSpeedY = $speed * -$bounceAngle;
            } else {
                $this->player1Score++;
                $this->checkWinner();
                $this->ballSpeedX *= -1;
                $nextX = $this->canvasWidth - $this->ballSize;
                $this->ballSpeedY = rand(-700, 700) / 100;
            }
        }

        foreach ($this->obstacles as $obstacle) {
            if (abs($nextX - $obstacle['x']) < $obstacle['size'] / 2 + $this->ballSize &&
                abs($nextY - $obstacle['y']) < $obstacle['size'] / 2 + $this->ballSize) {
                if (abs($nextX - $obstacle['x']) > abs($nextY - $obstacle['y'])) {
                    $this->ballSpeedX *= -1.1;
                } else {
                    $this->ballSpeedY *= -1.1;
                }
            }
        }

        $this->ballX = $nextX;
        $this->ballY = $nextY;
    }
}

class GameWebSocketHandler implements MessageComponentInterface {
    protected $clients;
    protected $gameState;
    protected $connections;
    protected $loop;

    public function __construct() {
        $this->clients = new SplObjectStorage;
        $this->gameState = new GameState();
        $this->connections = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $playerNumber = count($this->connections) + 1;
        
        if ($playerNumber <= 2) {
            $this->connections[$conn->resourceId] = $playerNumber;
            $conn->send(json_encode([
                'type' => 'init',
                'player_number' => $playerNumber
            ]));
        } else {
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        $playerNumber = $this->connections[$from->resourceId] ?? null;

        if (!$playerNumber) {
            return;
        }

        if ($data['type'] === 'start_game') {
            $this->gameState->startGame();
        } elseif ($data['type'] === 'movement') {
            $this->handleMovement($playerNumber, $data['key']);
        }

        $this->broadcast();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->connections[$conn->resourceId]);
        
        if (count($this->connections) === 0) {
            $this->gameState->resetGame();
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }

    protected function handleMovement($playerNumber, $key) {
        $speed = 100;
        $key = strtolower($key);
        
        if ($playerNumber === 1) {
            if ($key === 'w' && $this->gameState->player1Y > 0) {
                $this->gameState->player1Y -= $speed;
            } elseif ($key === 's' && $this->gameState->player1Y < $this->gameState->canvasHeight - $this->gameState->paddleHeight) {
                $this->gameState->player1Y += $speed;
            }
        } elseif ($playerNumber === 2) {
            if ($key === 'arrowup' && $this->gameState->player2Y > 0) {
                $this->gameState->player2Y -= $speed;
            } elseif ($key === 'arrowdown' && $this->gameState->player2Y < $this->gameState->canvasHeight - $this->gameState->paddleHeight) {
                $this->gameState->player2Y += $speed;
            }
        }
    }

    protected function broadcast() {
        $gameState = $this->gameState->toArray();
        foreach ($this->clients as $client) {
            $client->send(json_encode($gameState));
        }
    }

    public function startGameLoop() {
        $this->loop = \React\EventLoop\Factory::create();
        $this->loop->addPeriodicTimer(1/60, function () {
            $this->gameState->update();
            $this->broadcast();
        });
        $this->loop->run();
    }
}