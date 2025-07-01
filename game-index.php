<?php
// Start a session to store the game state for this specific user.
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login_page.php');
    exit();
}

// If the user requests a new game, reset their session state.
if (isset($_GET['new_game'])) {
    unset($_SESSION['game_state']);
    header('Location: game-index.php'); // Redirect to clear the GET parameter
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>校园塔防 (PHP版)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="game-style.css">
    <style>
        /* Add a specific style for the leader's projectile */
        .watermelon-projectile {
            position: absolute;
            font-size: 32px;
            z-index: 15;
            pointer-events: none;
            transition: left 0.5s linear, top 0.5s linear;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">校园塔防</div>
        
        <div class="ui-info-card">
            <div class="resource">
                <span>蔬菜:</span>
                <div class="vegetable-icon" id="ui-veg-icon"></div>
                <span id="vegetable-count">10</span>
            </div>
            <div class="resource">
                <span>生命:</span>
                 <div class="health-bar-container">
                    <div class="health-fill" id="health-fill"></div>
                </div>
                <span id="health-count">100</span>
            </div>
            <div class="difficulty-progress">
                <span>下波增强:</span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="difficulty-progress-fill"></div>
                </div>
            </div>
        </div>

        <div class="controls">
            <button id="start-btn">开始游戏</button>
            <button id="restart-btn" onclick="window.location.href='game_menu.php'">返回菜单</button>
        </div>
        
        <div class="game-area" id="game-area">
            <div class="grid-container" id="grid-container"></div>
            <div class="boost-indicator" id="boost-indicator"></div>
            <div class="player-booster" id="player-booster"></div>

             <div class="tower-shop">
                <div class="tower-option" data-tower-type="spice">
                    <div class="tower-name">调料女生</div>
                    <div id="spice-girl-icon" class="tower-girl-icon"></div>
                    <div class="tower-cost">
                        <div class="vegetable-icon"></div>
                        <span>4 蔬菜</span>
                    </div>
                    <div class="cooldown-progress"></div>
                </div>
                <div class="tower-option" data-tower-type="producer">
                     <div class="tower-name">生产女生</div>
                    <div id="producer-girl-icon" class="tower-girl-icon"></div>
                    <div class="tower-cost">
                        <div class="vegetable-icon"></div>
                        <span>2 蔬菜</span>
                    </div>
                    <div class="cooldown-progress"></div>
                </div>
                <div class="tower-option" data-tower-type="bomb">
                     <div class="tower-name">爆炸女生</div>
                    <div id="bomb-girl-icon" class="tower-girl-icon"></div>
                    <div class="tower-cost">
                        <div class="vegetable-icon"></div>
                        <span>10 蔬菜</span>
                    </div>
                    <div class="cooldown-progress"></div>
                </div>
                <div class="tower-option" data-tower-type="cherryBomb">
                     <div class="tower-name">樱桃女生</div>
                    <div id="cherry-girl-icon" class="tower-girl-icon"></div>
                    <div class="tower-cost">
                        <div class="vegetable-icon"></div>
                        <span>20 蔬菜</span>
                    </div>
                    <div class="cooldown-progress"></div>
                </div>
                <div class="tower-option" data-tower-type="shield">
                    <div class="tower-name">护盾女生</div>
                   <div id="shield-girl-icon" class="tower-girl-icon"></div>
                   <div class="tower-cost">
                       <div class="vegetable-icon"></div>
                       <span>6 蔬菜</span>
                   </div>
                   <div class="cooldown-progress"></div>
               </div>
            </div>
        </div>
    </div>
    
    <div class="game-over" id="game-over">
        <div class="game-over-content">
            <h2>游戏结束</h2>
            <p id="result-message">防线被突破了！</p>
            <button id="play-again-btn" onclick="window.location.href='game_menu.php'">返回菜单</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
    // --- CONSTANTS AND CONFIG (Mirrors server) ---
    const TOWER_INFO = {
        spice: { cost: 4, cooldown: 2 },
        bomb: { cost: 10, cooldown: 6 },
        producer: { cost: 2, cooldown: 2 },
        cherryBomb: { cost: 20, cooldown: 6 },
        shield: { cost: 6, cooldown: 20 },
    };
    const CELL_SIZE = 95;
    const GRID_ROWS = 6;
    const GRID_COLS = 12;
    const PROTECTED_COLUMN = 0;

    const dom = {
        gameArea: document.getElementById('game-area'),
        gridContainer: document.getElementById('grid-container'),
        vegetableCount: document.getElementById('vegetable-count'),
        healthCount: document.getElementById('health-count'),
        healthFill: document.getElementById('health-fill'),
        towerOptions: document.querySelectorAll('.tower-option'),
        startBtn: document.getElementById('start-btn'),
        restartBtn: document.getElementById('restart-btn'),
        playAgainBtn: document.getElementById('play-again-btn'),
        gameOverScreen: document.getElementById('game-over'),
        difficultyProgressFill: document.getElementById('difficulty-progress-fill'),
        playerBooster: document.getElementById('player-booster'),
        boostIndicator: document.getElementById('boost-indicator'),
        vegIcon: document.getElementById('ui-veg-icon'),
    };

    let selectedTower = null;
    let gameLoopInterval = null;

    function initializeGrid() {
        dom.gridContainer.innerHTML = '';
        dom.gridContainer.style.width = `${GRID_COLS * CELL_SIZE}px`;
        for (let r = 0; r < GRID_ROWS; r++) {
            const rowEl = document.createElement('div');
            rowEl.className = 'grid';
            for (let c = 0; c < GRID_COLS; c++) {
                const cell = document.createElement('div');
                cell.className = 'cell';
                cell.dataset.row = r;
                cell.dataset.col = c;
                if (c === PROTECTED_COLUMN) {
                    cell.classList.add('protected-cell');
                    const protectedGirl = document.createElement('div');
                    protectedGirl.className = 'protected-girl';
                    cell.appendChild(protectedGirl);
                }
                cell.addEventListener('click', () => handleCellClick(r, c));
                rowEl.appendChild(cell);
            }
            dom.gridContainer.appendChild(rowEl);
        }
    }

    async function sendAction(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        for (const key in data) { formData.append(key, data[key]); }
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            return await response.json();
        } catch (error) { console.error('Error communicating with server:', error); }
    }
    
    function startGame() {
        dom.startBtn.disabled = true;
        sendAction('start_game').then(() => {
            gameLoopInterval = setInterval(getGameState, 100);
            document.addEventListener('keydown', handlePlayerInput);
            dom.boostIndicator.style.display = 'block';
        });
    }

    function getGameState() {
        sendAction('get_state').then(state => {
            if (state) { render(state); }
        });
    }

    dom.startBtn.addEventListener('click', startGame);
    dom.towerOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            const type = opt.dataset.towerType;
            selectedTower = (selectedTower === type) ? null : type;
            updateTowerSelectionUI();
        });
    });

    function handleCellClick(row, col) {
        if (!selectedTower) return;
        sendAction('place_tower', { type: selectedTower, row, col }).then(state => {
            if (state) render(state);
        });
        selectedTower = null;
        updateTowerSelectionUI();
    }
    
    function handlePlayerInput(e) {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const direction = e.key === 'ArrowLeft' ? 'left' : 'right';
            sendAction('move_booster', { direction });
        }
    }

    function render(state) {
        dom.vegetableCount.textContent = state.vegetables;
        dom.healthCount.textContent = state.health;
        dom.healthFill.style.width = `${state.health}%`;

        if (state.gameOver) {
            clearInterval(gameLoopInterval);
            dom.gameOverScreen.classList.add('show');
            return;
        }

        const timeToNext = state.nextDifficultyIncreaseTime - state.lastUpdate;
        const progress = 100 - (timeToNext / state.difficultyIncreaseInterval * 100);
        dom.difficultyProgressFill.style.width = `${Math.min(100, Math.max(0, progress))}%`;
        
        const now = state.lastUpdate;
        dom.towerOptions.forEach(opt => {
            const type = opt.dataset.towerType;
            const cooldownEnd = state.towerCooldowns[type];
            const cooldownInfo = TOWER_INFO[type];
            opt.classList.toggle('insufficient-funds', state.vegetables < cooldownInfo.cost);
            const progressEl = opt.querySelector('.cooldown-progress');
            if (now < cooldownEnd) {
                opt.classList.add('on-cooldown');
                const duration = TOWER_INFO[type].cooldown;
                const remaining = cooldownEnd - now;
                const progress = Math.max(0, 100 - (remaining / duration) * 100);
                progressEl.style.width = `${100 - progress}%`;
            } else {
                opt.classList.remove('on-cooldown');
                progressEl.style.width = `0%`;
            }
        });

        // Reconcile Towers
        const serverTowers = Object.values(state.towers);
        const clientTowers = document.querySelectorAll('.tower');
        let towerIdsOnClient = new Set();
        
        clientTowers.forEach(tEl => {
            const id = tEl.dataset.id;
            const towerData = serverTowers.find(t => t.id === id);
            if (towerData) {
                 const cell = dom.gridContainer.children[towerData.row]?.querySelector(`[data-col='${towerData.col}']`);
                if (cell && tEl.parentElement !== cell) {
                    cell.appendChild(tEl); // Move tower if column changed (e.g. by rope enemy)
                }
                tEl.classList.toggle('shielded', !!towerData.shielded);
                towerIdsOnClient.add(id);
            } else {
                tEl.remove();
            }
        });
        
        serverTowers.forEach(towerData => {
            if (!towerIdsOnClient.has(towerData.id)) {
                const cell = dom.gridContainer.children[towerData.row]?.querySelector(`[data-col='${towerData.col}']`);
                if (cell) {
                    const towerEl = document.createElement('div');
                    towerEl.className = `tower ${towerData.type}-tower`;
                    towerEl.dataset.id = towerData.id;
                    cell.appendChild(towerEl);
                }
            }
        });

        // Reconcile Enemies
        const serverEnemies = Object.values(state.enemies);
        const clientEnemies = document.querySelectorAll('.enemy');
        let enemyIdsOnClient = new Set();

        clientEnemies.forEach(eEl => {
            const id = eEl.dataset.id;
            const enemyData = serverEnemies.find(e => e.id === id);
            if (enemyData) {
                eEl.style.left = `${enemyData.position * CELL_SIZE}px`;
                const healthFill = eEl.querySelector('.health-fill');
                if (healthFill) {
                    healthFill.style.width = `${(enemyData.health / enemyData.maxHealth) * 100}%`;
                }
                enemyIdsOnClient.add(id);
            } else {
                eEl.remove();
            }
        });

        serverEnemies.forEach(enemyData => {
            if (!enemyIdsOnClient.has(enemyData.id)) {
                createEnemyElement(enemyData);
            }
        });

        // Handle one-time events
        state.events.forEach(event => {
            switch(event.type) {
                case 'explosion': createExplosionAnimation(event.row, event.col); break;
                case 'message': showGameMessage(event.text); break;
                case 'bomber_attack': triggerBomberWarning(event.targetRow, event.targetCol); break;
                case 'producer_veg': createProducerVegAnimation(event.row, event.col); break;
                case 'attack': createAttackAnimation(event.from, event.to_id); break;
                // NEW EVENT HANDLERS
                case 'heal_pulse': createHealPulseAnimation(event.id); break;
                case 'heal_receive': createHealReceiveAnimation(event.target_id); break;
                case 'rope_pull': createRopePullAnimation(event.from_id, event.to_id); break;
                case 'leader_attack': createWatermelonAnimation(event.from_id, event.to_id); break;
            }
        });

        updateBoosterPosition(state.boosterColumn);
    }
    
    function createEnemyElement(enemy) {
        const enemyEl = document.createElement('div');
        enemyEl.className = `enemy ${enemy.type}-enemy`;
        enemyEl.id = enemy.id;
        enemyEl.dataset.id = enemy.id;
        const healthBar = document.createElement('div');
        healthBar.className = 'health-bar';
        const healthFillEl = document.createElement('div');
        healthFillEl.className = 'health-fill';
        healthBar.appendChild(healthFillEl);
        enemyEl.appendChild(healthBar);
        dom.gridContainer.children[enemy.row]?.appendChild(enemyEl);
        if (enemyEl.parentElement) enemyEl.style.left = `${enemy.position * CELL_SIZE}px`;
    }

    function createProducerVegAnimation(row, col) {
        const vegEl = document.createElement('div');
        vegEl.className = 'producer-veg-animation';
        const startX = col * CELL_SIZE + CELL_SIZE / 2;
        const startY = row * CELL_SIZE + CELL_SIZE / 2;
        vegEl.style.left = `${startX}px`;
        vegEl.style.top = `${startY}px`;
        dom.gridContainer.appendChild(vegEl);
        setTimeout(() => {
            const vegIconRect = dom.vegIcon.getBoundingClientRect();
            const gridRect = dom.gridContainer.getBoundingClientRect();
            const endX = vegIconRect.left - gridRect.left + vegIconRect.width / 2;
            const endY = vegIconRect.top - gridRect.top + vegIconRect.height / 2;
            vegEl.style.transform = `translate(${endX - startX}px, ${endY - startY}px) scale(0.5)`;
            vegEl.style.opacity = '0';
        }, 50);
        setTimeout(() => vegEl.remove(), 1250);
    }

    function createAttackAnimation(from, toId) {
        const targetEl = document.getElementById(toId);
        if (!targetEl) return;
        const projectileEl = document.createElement('div');
        projectileEl.className = 'projectile';
        const startX = from.col * CELL_SIZE + CELL_SIZE / 2;
        const startY = from.row * CELL_SIZE + CELL_SIZE / 2;
        const targetRect = targetEl.getBoundingClientRect();
        const gridRect = dom.gridContainer.getBoundingClientRect();
        const endX = targetRect.left - gridRect.left + targetRect.width / 2;
        const endY = targetRect.top - gridRect.top + targetRect.height / 2;
        projectileEl.style.left = `${startX}px`;
        projectileEl.style.top = `${startY}px`;
        dom.gridContainer.appendChild(projectileEl);
        setTimeout(() => {
            projectileEl.style.left = `${endX}px`;
            projectileEl.style.top = `${endY}px`;
        }, 10);
        setTimeout(() => {
            projectileEl.remove();
            createShatterEffect(endX, endY);
        }, 160);
    }
    
    function createShatterEffect(x, y) {
        const shatterEl = document.createElement('div');
        shatterEl.className = 'shatter-effect';
        shatterEl.style.left = `${x}px`;
        shatterEl.style.top = `${y}px`;
        dom.gridContainer.appendChild(shatterEl);
        setTimeout(() => shatterEl.remove(), 300);
    }
    
    // --- NEW ANIMATION FUNCTIONS ---
    function createHealPulseAnimation(healerId) {
        const healerEl = document.getElementById(healerId);
        if (!healerEl) return;
        const healEl = document.createElement('div');
        healEl.className = 'heal-animation';
        healEl.style.left = healerEl.style.left;
        healEl.style.top = `${CELL_SIZE / 2}px`;
        healerEl.parentElement.appendChild(healEl);
        setTimeout(() => healEl.remove(), 1000);
    }

    function createHealReceiveAnimation(targetId) {
        const enemyEl = document.getElementById(targetId);
        if (!enemyEl) return;
        const healReceiveEl = document.createElement('div');
        healReceiveEl.className = 'heal-receive-animation';
        healReceiveEl.textContent = '+';
        enemyEl.appendChild(healReceiveEl);
        setTimeout(() => healReceiveEl.remove(), 800);
    }

    function createRopePullAnimation(enemyId, towerId) {
        const towerEl = document.querySelector(`.tower[data-id='${towerId}']`);
        const enemyEl = document.getElementById(enemyId);
        if(!towerEl || !enemyEl) return;

        const ropeEl = document.createElement('div');
        ropeEl.className = 'rope-animation';
        
        const towerRect = towerEl.getBoundingClientRect();
        const enemyRect = enemyEl.getBoundingClientRect();
        const gridRect = dom.gridContainer.getBoundingClientRect();

        const startX = towerRect.left - gridRect.left + towerRect.width / 2;
        const endX = enemyRect.left - gridRect.left + enemyRect.width / 2;
        const width = endX - startX;
        
        ropeEl.style.left = `${startX}px`;
        ropeEl.style.top = `${CELL_SIZE / 2 - 2}px`;
        ropeEl.style.width = `${width}px`;
        
        enemyEl.parentElement.appendChild(ropeEl);
        setTimeout(() => ropeEl.remove(), 400);
    }

    function createWatermelonAnimation(leaderId, towerId) {
        const leaderEl = document.getElementById(leaderId);
        const towerEl = document.querySelector(`.tower[data-id='${towerId}']`);
        if (!leaderEl || !towerEl) return;

        const projectileEl = document.createElement('div');
        projectileEl.className = 'watermelon-projectile';
        projectileEl.textContent = '🍉';

        const leaderRect = leaderEl.getBoundingClientRect();
        const towerRect = towerEl.getBoundingClientRect();
        const gridRect = dom.gridContainer.getBoundingClientRect();

        const startX = leaderRect.left - gridRect.left + leaderRect.width / 2;
        const startY = leaderRect.top - gridRect.top + leaderRect.height / 2;
        const endX = towerRect.left - gridRect.left + towerRect.width / 2;
        const endY = towerRect.top - gridRect.top + towerRect.height / 2;

        projectileEl.style.left = `${startX}px`;
        projectileEl.style.top = `${startY}px`;
        dom.gridContainer.appendChild(projectileEl);

        setTimeout(() => {
            projectileEl.style.left = `${endX}px`;
            projectileEl.style.top = `${endY}px`;
        }, 10);

        setTimeout(() => {
            projectileEl.remove();
            towerEl.style.transition = 'transform 0.3s, opacity 0.3s';
            towerEl.style.transform = 'scale(0)';
            towerEl.style.opacity = '0';
        }, 510);
    }
    // --- END NEW ANIMATION FUNCTIONS ---

    function createExplosionAnimation(row, col) {
        const explosionEl = document.createElement('div');
        explosionEl.className = 'explosion-animation';
        explosionEl.style.left = `${col * CELL_SIZE + CELL_SIZE / 2}px`;
        explosionEl.style.top = `${row * CELL_SIZE + CELL_SIZE / 2}px`;
        dom.gridContainer.appendChild(explosionEl);
        setTimeout(() => explosionEl.remove(), 500);
    }
    
    function triggerBomberWarning(targetRow, targetCol) {
        const targetCells = [];
        for (let r = 0; r < 2; r++) {
            for (let c = 0; c < 2; c++) {
                const cell = dom.gridContainer.children[targetRow + r]?.querySelector(`[data-col='${targetCol + c}']`);
                if (cell) targetCells.push(cell);
            }
        }
        targetCells.forEach(cell => cell.classList.add('bomb-warning'));
        setTimeout(() => {
             targetCells.forEach(cell => cell.classList.remove('bomb-warning'));
        }, 2000);
    }

    function showGameMessage(msg) {
        const messageEl = document.createElement('div');
        messageEl.className = 'game-message';
        messageEl.textContent = msg;
        dom.gameArea.appendChild(messageEl);
        setTimeout(() => messageEl.classList.add('show'), 10);
        setTimeout(() => {
            messageEl.classList.remove('show');
            setTimeout(() => messageEl.remove(), 500);
        }, 2800);
    }

    function updateTowerSelectionUI() {
        dom.towerOptions.forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.towerType === selectedTower);
        });
    }

    function updateBoosterPosition(boosterColumn) {
        const gridRect = dom.gridContainer.getBoundingClientRect();
        const gameAreaRect = dom.gameArea.getBoundingClientRect();
        const gridOffsetX = gridRect.left - gameAreaRect.left;
        
        const boosterX = gridOffsetX + (boosterColumn * CELL_SIZE) + (CELL_SIZE / 2) - (dom.playerBooster.offsetWidth / 2);
        const boosterY = gridRect.top - gameAreaRect.top + gridRect.height + 10;
        const indicatorX = gridOffsetX + (boosterColumn * CELL_SIZE) + (CELL_SIZE / 2) - (dom.boostIndicator.offsetWidth / 2);
        const indicatorY = gridRect.top - gameAreaRect.top;
        
        dom.playerBooster.style.left = `${boosterX}px`;
        dom.playerBooster.style.top = `${boosterY}px`;
        dom.boostIndicator.style.left = `${indicatorX}px`;
        dom.boostIndicator.style.top = `${indicatorY}px`;
        dom.boostIndicator.style.height = `${gridRect.height}px`;
    }

    initializeGrid();
    getGameState();
});
    </script>
</body>
</html>