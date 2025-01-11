import React, { useEffect, useRef, useState } from 'react';

const PongGame = () => {
  const canvasRef = useRef(null);
  const [websocket, setWebsocket] = useState(null);
  const [playerNumber, setPlayerNumber] = useState(null);
  const [gameState, setGameState] = useState({
    player1Y: 250,
    player2Y: 250,
    ballX: 400,
    ballY: 300,
    player1Score: 0,
    player2Score: 0,
    game_started: false,
    running: false,
    winner: null,
    obstacles: []
  });

  const CANVAS_WIDTH = 800;
  const CANVAS_HEIGHT = 600;
  const PADDLE_WIDTH = 10;
  const PADDLE_HEIGHT = 100;
  const BALL_SIZE = 10;

  useEffect(() => {
   
    const ws = new WebSocket('ws://localhost:8000/ws');

    ws.onopen = () => {
      console.log('Connected to server');
    };

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        
        if (data.type === 'init') {
          setPlayerNumber(data.player_number);
        } else {
          setGameState(data);
        }
      } catch (error) {
        console.error('Error parsing message:', error);
      }
    };

    ws.onclose = () => {
      console.log('Disconnected from server');
    };

    setWebsocket(ws);

    return () => ws.close();
  }, []);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    
    const render = () => {
    
      ctx.fillStyle = 'black';
      ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

     
      ctx.fillStyle = 'white';
      ctx.fillRect(0, gameState.player1Y, PADDLE_WIDTH, PADDLE_HEIGHT);
      ctx.fillRect(CANVAS_WIDTH - PADDLE_WIDTH, gameState.player2Y, PADDLE_WIDTH, PADDLE_HEIGHT);

     
      ctx.beginPath();
      ctx.arc(gameState.ballX, gameState.ballY, BALL_SIZE, 0, Math.PI * 2);
      ctx.fillStyle = 'white';
      ctx.fill();
      ctx.closePath();

  
      ctx.fillStyle = 'red';
      gameState.obstacles.forEach(obstacle => {
        ctx.fillRect(
          obstacle.x - obstacle.size / 2,
          obstacle.y - obstacle.size / 2,
          obstacle.size,
          obstacle.size
        );
      });

     
      ctx.fillStyle = 'white';
      ctx.font = '24px Arial';
      ctx.textAlign = 'center';
      ctx.fillText(gameState.player1Score, CANVAS_WIDTH * 0.25, 30);
      ctx.fillText(gameState.player2Score, CANVAS_WIDTH * 0.75, 30);

      
      ctx.fillStyle = 'white';
      ctx.font = '20px Arial';
      ctx.textAlign = 'center';
      
      if (gameState.winner) {
        ctx.font = '30px Arial';
        ctx.fillStyle = '#4ade80';
        ctx.fillText(`Player ${gameState.winner} Wins!`, CANVAS_WIDTH/2, CANVAS_HEIGHT/2);
        ctx.font = '20px Arial';
        ctx.fillText('Press Start for a new game', CANVAS_WIDTH/2, CANVAS_HEIGHT/2 + 40);
      } else if (!gameState.game_started) {
        if (!playerNumber) {
          ctx.fillText('Connecting...', CANVAS_WIDTH/2, CANVAS_HEIGHT/2);
        } else if (playerNumber > 2) {
          ctx.fillText('Game is full', CANVAS_WIDTH/2, CANVAS_HEIGHT/2);
        } else {
          ctx.fillText('Press Start to begin', CANVAS_WIDTH/2, CANVAS_HEIGHT/2);
        }
      }
    };

    const gameLoop = setInterval(render, 1000/60);
    return () => clearInterval(gameLoop);
  }, [gameState]);

  useEffect(() => {
    const handleKeyPress = (e) => {
      if (!websocket || !playerNumber || (!gameState.running && !gameState.winner)) return;

      websocket.send(JSON.stringify({
        type: 'movement',
        key: e.key
      }));
    };

    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, [websocket, playerNumber, gameState.running, gameState.winner]);

  const handleStartGame = () => {
    if (websocket && websocket.readyState === WebSocket.OPEN && (!gameState.running || gameState.winner)) {
      websocket.send(JSON.stringify({ type: 'start_game' }));
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-gray-900">
      <div className="mb-4 text-white text-xl">
        {playerNumber && `You are Player ${playerNumber}`}
      </div>
      <div className="mb-2 text-white text-lg">
        First to 10 points wins!
      </div>
      <canvas
        ref={canvasRef}
        width={CANVAS_WIDTH}
        height={CANVAS_HEIGHT}
        className="border-2 border-white"
      />
      <div className="mt-4 flex flex-col items-center gap-4">
        <button
          onClick={handleStartGame}
          disabled={!playerNumber || (gameState.running && !gameState.winner)}
          className={`px-4 py-2 rounded ${
            !playerNumber || (gameState.running && !gameState.winner)
              ? 'bg-gray-500' 
              : 'bg-blue-500 hover:bg-blue-600'
          } text-white`}
        >
          {gameState.winner 
            ? 'New Game' 
            : gameState.running 
              ? 'Game In Progress' 
              : 'Start Game'}
        </button>
        <div className="text-white text-sm">
          Player 1: W/S keys | Player 2: Up/Down arrows
        </div>
      </div>
    </div>
  );
};

export default PongGame;