(function () {
    'use strict';

    var root = document.getElementById('quizWorld');
    var canvas = document.getElementById('worldCanvas');
    if (!root || !canvas) {
        return;
    }

    var config;
    try {
        config = JSON.parse(root.dataset.world || '{}');
    } catch (error) {
        console.error('Data arena tidak dapat dibaca.', error);
        return;
    }

    var context = canvas.getContext('2d');
    var width = 0;
    var height = 0;
    var pixelRatio = 1;
    var worldWidth = 3600;
    var worldHeight = 2400;
    var tileSize = 48;
    var player = { x: 1800, y: 2040, direction: 'up', walkTime: 0 };
    var controls = { x: 0, y: 0 };
    var keys = Object.create(null);
    var lastFrame = 0;
    var timerBaseElapsed = Math.max(0, Number(config.timerElapsed) || 0);
    var timerStartedAt = performance.now();
    var timerStoppedAt = null;
    var activeQuestion = null;
    var isSubmitting = false;
    var worldNotice = '';
    var worldNoticeUntil = 0;
    var worldNoticeTimer = null;
    var soundEnabled = true;
    var audioContext = null;
    var questionPins = [];
    var remotePlayers = Object.create(null);
    var syncInFlight = false;
    var sessionFinishRequested = false;
    var frameDelta = 0;
    var sessionDeadlinePerformance = performance.now() + Math.max(0, Number(config.sessionRemaining) || 0) * 1000;
    var building = { x: 800, y: 260, width: 2000, height: 1460 };
    var mainDoor = { x: 1800, width: 280, open: 0 };
    var rooms = [
        { x: 860, y: 330, width: 400, height: 450, title: 'Ruang Kelas', color: '#b7936d', doorSide: 'south', doorOpen: 0 },
        { x: 1320, y: 330, width: 400, height: 450, title: 'Lab Komputer', color: '#7fa5b5', doorSide: 'south', doorOpen: 0 },
        { x: 1780, y: 330, width: 400, height: 450, title: 'Perpustakaan', color: '#9e83ad', doorSide: 'south', doorOpen: 0 },
        { x: 2240, y: 330, width: 400, height: 450, title: 'Kantin', color: '#c69a72', doorSide: 'south', doorOpen: 0 },
        { x: 870, y: 1070, width: 430, height: 500, title: 'Aula Kampus', color: '#9b8ab1', doorSide: 'north', doorOpen: 0 },
        { x: 1340, y: 1070, width: 290, height: 500, title: 'Lab Sains', color: '#8aab9a', doorSide: 'north', doorOpen: 0 },
        { x: 1980, y: 1070, width: 430, height: 500, title: 'Mushola', color: '#8fae9a', doorSide: 'north', doorOpen: 0 }
    ];
    var trees = [
        [250, 360], [500, 530], [330, 930], [560, 1450], [650, 1910],
        [3020, 360], [3290, 620], [3120, 1120], [3330, 1570],
        [2930, 1950], [650, 2260], [1050, 2240], [2700, 2200], [3170, 2240]
    ];
    var obstacles = trees.map(function (tree) {
        return { x: tree[0], y: tree[1], radius: 25 };
    });
    var furnitureObstacles = [];
    var benchPositions = [[420, 1100], [3130, 1020], [1250, 2110], [2400, 2110]];

    function addFurnitureRect(x, y, width, height) {
        furnitureObstacles.push({ x: x, y: y, width: width, height: height });
    }

    function createFurnitureObstacles() {
        rooms.forEach(function (room) {
            if (room.title === 'Ruang Kelas') {
                addFurnitureRect(room.x + room.width / 2 - 54, room.y + 82, 108, 18);
                for (var desk = 0; desk < 4; desk += 1) {
                    addFurnitureRect(
                        room.x + 52 + (desk % 2) * 145,
                        room.y + 127 + Math.floor(desk / 2) * 65,
                        65,
                        22
                    );
                }
            } else if (room.title === 'Lab Komputer') {
                for (var station = 0; station < 4; station += 1) {
                    var sx = room.x + 43 + (station % 2) * 150;
                    var sy = room.y + 112 + Math.floor(station / 2) * 72;
                    addFurnitureRect(sx, sy, 65, 22);
                    addFurnitureRect(sx + 20, sy - 16, 34, 21);
                }
            } else if (room.title === 'Perpustakaan') {
                for (var shelf = 0; shelf < 3; shelf += 1) {
                    addFurnitureRect(room.x + 32, room.y + 82 + shelf * 56, 42, 42);
                }
                addFurnitureRect(room.x + 130, room.y + 136, 65, 22);
                addFurnitureRect(room.x + 220, room.y + 136, 65, 22);
            } else if (room.title === 'Aula Kampus') {
                for (var seat = 0; seat < 4; seat += 1) {
                    addFurnitureRect(room.x + 48 + (seat % 2) * 145, room.y + 115 + Math.floor(seat / 2) * 55, 74, 20);
                }
            } else if (room.title === 'Lab Sains') {
                for (var bench = 0; bench < 3; bench += 1) {
                    addFurnitureRect(room.x + 42 + (bench % 2) * 145, room.y + 112 + Math.floor(bench / 2) * 66, 75, 25);
                    addFurnitureRect(room.x + 56 + (bench % 2) * 145, room.y + 96 + Math.floor(bench / 2) * 66, 22, 16);
                }
            } else if (room.title === 'Kantin') {
                addFurnitureRect(room.x + 45, room.y + 90, 72, 26);
                addFurnitureRect(room.x + 140, room.y + 90, 72, 26);
                addFurnitureRect(room.x + 95, room.y + 168, 72, 24);
            } else if (room.title === 'Mushola') {
                addFurnitureRect(room.x + room.width / 2 - 28, room.y + 66, 56, 22);
                addFurnitureRect(room.x + room.width - 74, room.y + 72, 48, 34);
            }
        });
    }

    function roomWalls(room) {
        var thickness = 16;
        var doorCenter = room.x + room.width / 2;
        var doorWidth = 78;
        var top = room.doorSide === 'north'
            ? [
                { x: room.x, y: room.y, width: (room.width - doorWidth) / 2, height: thickness },
                { x: doorCenter + doorWidth / 2, y: room.y, width: (room.width - doorWidth) / 2, height: thickness }
            ]
            : [{ x: room.x, y: room.y, width: room.width, height: thickness }];
        var bottom = room.doorSide === 'south'
            ? [
                { x: room.x, y: room.y + room.height - thickness, width: (room.width - doorWidth) / 2, height: thickness },
                { x: doorCenter + doorWidth / 2, y: room.y + room.height - thickness, width: (room.width - doorWidth) / 2, height: thickness }
            ]
            : [{ x: room.x, y: room.y + room.height - thickness, width: room.width, height: thickness }];
        return [
            ...top,
            { x: room.x, y: room.y, width: thickness, height: room.height },
            { x: room.x + room.width - thickness, y: room.y, width: thickness, height: room.height },
            ...bottom
        ];
    }

    function roomDoorRect(room) {
        return {
            x: room.x + room.width / 2 - 39,
            y: room.doorSide === 'north' ? room.y - 2 : room.y + room.height - 14,
            width: 78,
            height: 16
        };
    }

    function buildingWalls() {
        var thickness = 22;
        var bottom = building.y + building.height - thickness;
        var gateStart = mainDoor.x - mainDoor.width / 2;
        var gateEnd = mainDoor.x + mainDoor.width / 2;
        return [
            { x: building.x, y: building.y, width: building.width, height: thickness },
            { x: building.x, y: building.y, width: thickness, height: building.height },
            { x: building.x + building.width - thickness, y: building.y, width: thickness, height: building.height },
            { x: building.x, y: bottom, width: gateStart - building.x, height: thickness },
            { x: gateEnd, y: bottom, width: building.x + building.width - gateEnd, height: thickness }
        ];
    }

    function mainDoorRect() {
        return {
            x: mainDoor.x - mainDoor.width / 2,
            y: building.y + building.height - 22,
            width: mainDoor.width,
            height: 22
        };
    }

    function resizeCanvas() {
        var bounds = canvas.getBoundingClientRect();
        width = Math.max(1, bounds.width);
        height = Math.max(1, bounds.height);
        pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.round(width * pixelRatio);
        canvas.height = Math.round(height * pixelRatio);
        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    }

    function roundRect(ctx, x, y, w, h, radius) {
        var r = Math.min(radius, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function drawGrass(cameraX, cameraY) {
        context.fillStyle = '#527c62';
        context.fillRect(cameraX, cameraY, width, height);
        var firstTileX = Math.floor(cameraX / tileSize) * tileSize;
        var firstTileY = Math.floor(cameraY / tileSize) * tileSize;
        for (var y = firstTileY; y < cameraY + height + tileSize; y += tileSize) {
            for (var x = firstTileX; x < cameraX + width + tileSize; x += tileSize) {
                var noise = ((x * 17 + y * 31) % 23);
                context.fillStyle = noise < 7 ? 'rgba(172, 210, 135, .055)' : 'rgba(20, 54, 44, .045)';
                context.fillRect(x + 3, y + 4, 2, 2);
                context.fillRect(x + 25, y + 20, 2, 2);
                if (noise < 4) {
                    context.fillRect(x + 36, y + 36, 3, 3);
                }
            }
        }

        context.save();
        context.strokeStyle = '#c4aa78';
        context.lineWidth = 110;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(1800, worldHeight);
        context.lineTo(1800, building.y + building.height + 16);
        context.stroke();
        context.strokeStyle = 'rgba(255, 242, 205, .2)';
        context.lineWidth = 3;
        context.setLineDash([12, 14]);
        context.stroke();
        context.restore();

        context.save();
        context.strokeStyle = '#b89d70';
        context.lineWidth = 50;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(building.x - 120, building.y + building.height + 75);
        context.lineTo(building.x + building.width + 120, building.y + building.height + 75);
        context.moveTo(building.x + building.width / 2, building.y + building.height + 75);
        context.lineTo(building.x + building.width / 2, worldHeight);
        context.stroke();
        context.restore();
        context.save();
        context.fillStyle = 'rgba(255,255,255,.28)';
        context.font = '700 12px system-ui, sans-serif';
        context.textAlign = 'center';
        context.fillText('HALAMAN KAMPUS', worldWidth / 2, worldHeight - 80);
        context.restore();
    }

    function drawTree(x, y, index) {
        context.save();
        context.fillStyle = 'rgba(16, 36, 31, .3)';
        context.beginPath();
        context.ellipse(x + 4, y + 22, 29, 13, 0, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = '#76543d';
        roundRect(context, x - 6, y + 4, 12, 29, 5);
        context.fill();
        context.fillStyle = index % 3 === 0 ? '#3d7552' : (index % 3 === 1 ? '#4b865c' : '#568e62');
        context.beginPath();
        context.arc(x, y - 3, 28, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = 'rgba(175, 217, 139, .42)';
        context.beginPath();
        context.arc(x - 9, y - 12, 11, 0, Math.PI * 2);
        context.fill();
        context.restore();
    }

    function drawBench(x, y) {
        context.save();
        context.fillStyle = 'rgba(25, 34, 31, .24)';
        roundRect(context, x + 3, y + 7, 62, 18, 5);
        context.fill();
        context.fillStyle = '#9b6b46';
        roundRect(context, x, y, 62, 11, 4);
        context.fill();
        context.fillStyle = '#654a3e';
        context.fillRect(x + 8, y + 9, 5, 15);
        context.fillRect(x + 49, y + 9, 5, 15);
        context.restore();
    }

    function drawCampusBuilding() {
        context.save();
        context.fillStyle = 'rgba(19, 28, 37, .34)';
        roundRect(context, building.x - 10, building.y + 14, building.width + 20, building.height + 18, 30);
        context.fill();
        context.fillStyle = '#59616b';
        roundRect(context, building.x - 5, building.y - 5, building.width + 10, building.height + 10, 25);
        context.fill();
        context.fillStyle = '#d5c9ad';
        context.fillRect(building.x + 18, building.y + 18, building.width - 36, building.height - 36);
        context.fillStyle = '#8d806d';
        context.fillRect(825, 800, 1950, 240);
        context.fillRect(1650, 1018, 300, 685);
        context.fillStyle = 'rgba(255,255,255,.17)';
        context.fillRect(840, 810, 1920, 8);
        context.fillRect(1660, 1035, 8, 650);
        context.fillStyle = '#fff0d3';
        context.font = '800 18px system-ui, sans-serif';
        context.textAlign = 'center';
        context.fillText('GEDUNG KAMPUS', building.x + building.width / 2, building.y + 34);
        context.fillStyle = '#f0dfc0';
        context.font = '700 11px system-ui, sans-serif';
        context.fillText('KORIDOR UTAMA', 1430, 930);
        context.save();
        context.translate(1810, 1350);
        context.rotate(-Math.PI / 2);
        context.fillText('LOBI GEDUNG', 0, 0);
        context.restore();

        var wallSegments = buildingWalls();
        context.fillStyle = '#3d454e';
        wallSegments.forEach(function (wall) {
            context.fillRect(wall.x, wall.y, wall.width, wall.height);
        });
        context.fillStyle = '#d8c8a9';
        context.fillRect(mainDoor.x - mainDoor.width / 2, building.y + building.height - 8, mainDoor.width, 15);
        var slide = mainDoor.open * (mainDoor.width / 2 - 8);
        context.fillStyle = '#523f35';
        context.fillRect(mainDoor.x - mainDoor.width / 2 - slide, building.y + building.height - 18, mainDoor.width / 2, 18);
        context.fillRect(mainDoor.x + slide, building.y + building.height - 18, mainDoor.width / 2, 18);
        context.fillStyle = '#c7aa7c';
        context.fillRect(mainDoor.x - mainDoor.width / 2 + 5 - slide, building.y + building.height - 16, mainDoor.width / 2 - 10, 14);
        context.fillRect(mainDoor.x + slide + 5, building.y + building.height - 16, mainDoor.width / 2 - 10, 14);
        context.fillStyle = '#352e2a';
        context.font = '700 12px system-ui, sans-serif';
        context.fillText('PINTU UTAMA', mainDoor.x, building.y + building.height + 43);
        context.restore();
    }

    function drawRoom(room) {
        context.save();
        context.fillStyle = 'rgba(25, 31, 33, .28)';
        roundRect(context, room.x - 7, room.y + 10, room.width + 14, room.height + 15, 12);
        context.fill();
        context.fillStyle = room.color;
        context.fillRect(room.x + 12, room.y + 12, room.width - 24, room.height - 24);
        context.strokeStyle = 'rgba(255,255,255,.08)';
        context.lineWidth = 1;
        for (var gx = room.x + 16; gx < room.x + room.width - 12; gx += 32) {
            context.beginPath();
            context.moveTo(gx, room.y + 12);
            context.lineTo(gx, room.y + room.height - 12);
            context.stroke();
        }
        for (var gy = room.y + 16; gy < room.y + room.height - 12; gy += 32) {
            context.beginPath();
            context.moveTo(room.x + 12, gy);
            context.lineTo(room.x + room.width - 12, gy);
            context.stroke();
        }

        var door = roomDoorRect(room);
        var doorX = room.x + room.width / 2;
        var doorY = room.doorSide === 'north' ? room.y - 7 : room.y + room.height - 10;
        var slide = room.doorOpen * 43;
        context.fillStyle = '#5c4d43';
        context.fillRect(doorX - 43 - slide, doorY, 43, 13);
        context.fillRect(doorX + slide, doorY, 43, 13);
        context.fillStyle = '#d7c6a2';
        context.fillRect(doorX - 36 - slide, doorY + 1, 36, 11);
        context.fillRect(doorX + slide + 7, doorY + 1, 36, 11);
        context.fillStyle = '#312d2c';
        context.beginPath();
        context.arc(doorX - 7 - slide, doorY + 6, 2, 0, Math.PI * 2);
        context.arc(doorX + 7 + slide, doorY + 6, 2, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = '#2c2c32';
        roundRect(context, room.x + 30, room.y + 25, room.width - 60, 32, 7);
        context.fill();
        context.fillStyle = '#f2e9db';
        context.font = '700 15px system-ui, sans-serif';
        context.textAlign = 'center';
        context.fillText(room.title, room.x + room.width / 2, room.y + 46);

        if (room.title === 'Ruang Kelas') {
            context.fillStyle = '#4f765c';
            roundRect(context, room.x + room.width / 2 - 54, room.y + 82, 108, 18, 4);
            context.fill();
            for (var desk = 0; desk < 4; desk += 1) {
                var dx = room.x + 52 + (desk % 2) * 145;
                var dy = room.y + 127 + Math.floor(desk / 2) * 65;
                drawDesk(dx, dy, '#927054');
            }
        } else if (room.title === 'Lab Komputer') {
            for (var station = 0; station < 4; station += 1) {
                var sx = room.x + 43 + (station % 2) * 150;
                var sy = room.y + 112 + Math.floor(station / 2) * 72;
                drawDesk(sx, sy, '#557184');
                context.fillStyle = '#273748';
                roundRect(context, sx + 20, sy - 16, 34, 21, 4);
                context.fill();
                context.fillStyle = '#79c9dd';
                context.fillRect(sx + 24, sy - 12, 26, 12);
            }
        } else if (room.title === 'Perpustakaan') {
            for (var shelf = 0; shelf < 3; shelf += 1) {
                var shelfY = room.y + 82 + shelf * 56;
                context.fillStyle = '#694d5e';
                roundRect(context, room.x + 32, shelfY, 42, 42, 4);
                context.fill();
                for (var book = 0; book < 4; book += 1) {
                    context.fillStyle = ['#d39074', '#e3c06e', '#86b7a4', '#91a5d6'][(book + shelf) % 4];
                    context.fillRect(room.x + 37 + book * 8, shelfY + 6, 6, 30);
                }
            }
            drawDesk(room.x + 130, room.y + 136, '#816653');
            drawDesk(room.x + 220, room.y + 136, '#816653');
        } else if (room.title === 'Aula Kampus') {
            for (var seat = 0; seat < 4; seat += 1) {
                drawDesk(room.x + 48 + (seat % 2) * 145, room.y + 115 + Math.floor(seat / 2) * 55, '#776b8d');
            }
        } else if (room.title === 'Lab Sains') {
            for (var bench = 0; bench < 3; bench += 1) {
                var bx = room.x + 42 + (bench % 2) * 145;
                var by = room.y + 112 + Math.floor(bench / 2) * 66;
                drawDesk(bx, by, '#668879');
                context.fillStyle = '#d8eee4';
                context.beginPath();
                context.arc(bx + 25, by - 7, 7, 0, Math.PI * 2);
                context.arc(bx + 48, by - 7, 5, 0, Math.PI * 2);
                context.fill();
            }
        } else if (room.title === 'Kantin') {
            drawDesk(room.x + 45, room.y + 90, '#9b6849');
            drawDesk(room.x + 140, room.y + 90, '#9b6849');
            drawDesk(room.x + 95, room.y + 168, '#816653');
            context.fillStyle = '#f2d89b';
            context.font = '700 11px system-ui, sans-serif';
            context.fillText('KANTIN', room.x + room.width / 2, room.y + 83);
        } else if (room.title === 'Mushola') {
            context.fillStyle = '#667e70';
            roundRect(context, room.x + room.width / 2 - 26, room.y + 67, 52, 25, 5);
            context.fill();
            context.fillStyle = '#e8d9b8';
            context.beginPath();
            context.arc(room.x + room.width / 2, room.y + 91, 24, Math.PI, 0);
            context.fill();
            context.fillStyle = '#f1dfb8';
            for (var row = 0; row < 4; row += 1) {
                var matY = room.y + 145 + row * 64;
                for (var column = 0; column < 4; column += 1) {
                    var matX = room.x + 55 + column * 78;
                    roundRect(context, matX, matY, 48, 34, 5);
                    context.fill();
                    context.strokeStyle = 'rgba(91, 111, 92, .4)';
                    context.stroke();
                    context.beginPath();
                    context.moveTo(matX + 7, matY + 17);
                    context.lineTo(matX + 41, matY + 17);
                    context.stroke();
                }
            }
            context.fillStyle = '#e8d8bd';
            context.font = '700 9px system-ui, sans-serif';
            context.fillText('ARAH KIBLAT', room.x + room.width / 2, room.y + 121);
            context.fillStyle = '#6f5848';
            roundRect(context, room.x + room.width - 74, room.y + 72, 48, 34, 3);
            context.fill();
            ['#d39074', '#e3c06e', '#86b7a4'].forEach(function (bookColor, bookIndex) {
                context.fillStyle = bookColor;
                context.fillRect(room.x + room.width - 69 + bookIndex * 12, room.y + 77, 9, 23);
            });
        }

        context.strokeStyle = 'rgba(58, 52, 47, .92)';
        context.lineWidth = 12;
        context.lineCap = 'square';
        context.beginPath();
        context.moveTo(room.x, room.y);
        context.lineTo(room.x + room.width, room.y);
        context.moveTo(room.x, room.y);
        context.lineTo(room.x, room.y + room.height);
        context.moveTo(room.x + room.width, room.y);
        context.lineTo(room.x + room.width, room.y + room.height);
        if (room.doorSide === 'south') {
            context.moveTo(room.x, room.y + room.height);
            context.lineTo(door.x, room.y + room.height);
            context.moveTo(door.x + door.width, room.y + room.height);
            context.lineTo(room.x + room.width, room.y + room.height);
        } else {
            context.moveTo(room.x, room.y + room.height);
            context.lineTo(room.x + room.width, room.y + room.height);
            context.moveTo(room.x, room.y);
            context.lineTo(door.x, room.y);
            context.moveTo(door.x + door.width, room.y);
            context.lineTo(room.x + room.width, room.y);
        }
        context.stroke();
        context.restore();
    }

    function drawDesk(x, y, color) {
        context.save();
        context.fillStyle = 'rgba(24, 24, 27, .16)';
        roundRect(context, x + 3, y + 6, 65, 26, 5);
        context.fill();
        context.fillStyle = color;
        roundRect(context, x, y, 65, 22, 5);
        context.fill();
        context.fillStyle = 'rgba(255,255,255,.15)';
        context.fillRect(x + 4, y + 3, 57, 3);
        context.restore();
    }

    function collidesAt(x, y) {
        var radius = 12;
        if (x < radius || y < radius || x > worldWidth - radius || y > worldHeight - radius) {
            return true;
        }
        var campusWalls = buildingWalls();
        for (var campusWallIndex = 0; campusWallIndex < campusWalls.length; campusWallIndex += 1) {
            var campusWall = campusWalls[campusWallIndex];
            var campusX = Math.max(campusWall.x, Math.min(x, campusWall.x + campusWall.width));
            var campusY = Math.max(campusWall.y, Math.min(y, campusWall.y + campusWall.height));
            var campusDx = x - campusX;
            var campusDy = y - campusY;
            if (campusDx * campusDx + campusDy * campusDy < radius * radius) {
                return true;
            }
        }
        if (mainDoor.open < .98) {
            var mainOpening = mainDoorRect();
            var mainX = Math.max(mainOpening.x, Math.min(x, mainOpening.x + mainOpening.width));
            var mainY = Math.max(mainOpening.y, Math.min(y, mainOpening.y + mainOpening.height));
            var mainDx = x - mainX;
            var mainDy = y - mainY;
            if (mainDx * mainDx + mainDy * mainDy < radius * radius) {
                return true;
            }
        }
        for (var roomIndex = 0; roomIndex < rooms.length; roomIndex += 1) {
            var walls = roomWalls(rooms[roomIndex]);
            for (var wallIndex = 0; wallIndex < walls.length; wallIndex += 1) {
                var wall = walls[wallIndex];
                var nearestX = Math.max(wall.x, Math.min(x, wall.x + wall.width));
                var nearestY = Math.max(wall.y, Math.min(y, wall.y + wall.height));
                var dx = x - nearestX;
                var dy = y - nearestY;
                if (dx * dx + dy * dy < radius * radius) {
                    return true;
                }
            }
            if (rooms[roomIndex].doorOpen < .98) {
                var door = roomDoorRect(rooms[roomIndex]);
                var doorX = Math.max(door.x, Math.min(x, door.x + door.width));
                var doorY = Math.max(door.y, Math.min(y, door.y + door.height));
                var doorDx = x - doorX;
                var doorDy = y - doorY;
                if (doorDx * doorDx + doorDy * doorDy < radius * radius) {
                    return true;
                }
            }
        }
        for (var obstacleIndex = 0; obstacleIndex < obstacles.length; obstacleIndex += 1) {
            var obstacle = obstacles[obstacleIndex];
            var ox = x - obstacle.x;
            var oy = y - obstacle.y;
            var minimumDistance = obstacle.radius + radius - 7;
            if (ox * ox + oy * oy < minimumDistance * minimumDistance) {
                return true;
            }
        }
        for (var furnitureIndex = 0; furnitureIndex < furnitureObstacles.length; furnitureIndex += 1) {
            var furniture = furnitureObstacles[furnitureIndex];
            var nearestX = Math.max(furniture.x, Math.min(x, furniture.x + furniture.width));
            var nearestY = Math.max(furniture.y, Math.min(y, furniture.y + furniture.height));
            var dx = x - nearestX;
            var dy = y - nearestY;
            if (dx * dx + dy * dy < radius * radius) {
                return true;
            }
        }
        return false;
    }

    function createPins() {
        var outdoorSpots = [
            [350, 800], [3210, 820], [650, 1810],
            [2940, 1810], [1200, 2200], [2300, 2290]
        ];
        var corridorSpots = [
            [1050, 920], [1450, 920], [2220, 920],
            [2570, 920], [1800, 1130], [1800, 1450]
        ];
        var roomCounts = rooms.map(function () { return 0; });
        questionPins = config.questions.map(function (question, index) {
            var zone = index % (rooms.length + 2);
            var x;
            var y;
            if (zone < rooms.length) {
                var room = rooms[zone];
                var sideInset = room.title === 'Lab Sains' ? 38 : 40;
                var roomSpots = [
                    [room.x + sideInset, room.y + 125],
                    [room.x + room.width - sideInset, room.y + 125],
                    [room.x + sideInset, room.y + room.height - 48],
                    [room.x + room.width - sideInset, room.y + room.height - 48]
                ];
                var roomSpot = roomSpots[roomCounts[zone]++ % roomSpots.length];
                x = roomSpot[0];
                y = roomSpot[1];
            } else if (zone === rooms.length) {
                var corridor = corridorSpots[Math.floor(index / (rooms.length + 2)) % corridorSpots.length];
                x = corridor[0];
                y = corridor[1];
            } else {
                var outdoor = outdoorSpots[Math.floor(index / (rooms.length + 2)) % outdoorSpots.length];
                x = outdoor[0];
                y = outdoor[1];
            }
            return {
                id: question.id,
                order: question.order,
                question: question.question,
                options: question.options,
                type: question.type,
                answered: question.answered,
                answeredByName: question.answeredByName || '',
                x: x,
                y: y
            };
        });
    }

    function drawPin(pin, time) {
        var isLocked = pin.answered || pin.locked;
        var pulse = isLocked ? 0 : (Math.sin(time / 300 + pin.order) + 1) * 2.5;
        context.save();
        context.shadowColor = isLocked ? 'rgba(91, 209, 147, .45)' : 'rgba(194, 177, 255, .68)';
        context.shadowBlur = isLocked ? 8 : 13 + pulse;
        context.fillStyle = isLocked ? '#56c58c' : '#b9a9ff';
        context.beginPath();
        context.arc(pin.x, pin.y, isLocked ? 16 : 15 + pulse * .2, 0, Math.PI * 2);
        context.fill();
        context.shadowBlur = 0;
        context.strokeStyle = 'rgba(255,255,255,.68)';
        context.lineWidth = 2;
        context.stroke();
        context.fillStyle = isLocked ? '#163c31' : '#292442';
        context.font = '800 12px system-ui, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(isLocked ? '✓' : String(pin.order), pin.x, pin.y + .5);
        if (!isLocked) {
            context.fillStyle = '#f3edff';
            context.font = '700 10px system-ui, sans-serif';
            context.fillText('SOAL', pin.x, pin.y - 27);
        } else if (pin.answeredByName) {
            context.fillStyle = '#e6fff1';
            context.font = '600 8px system-ui, sans-serif';
            context.fillText(pin.answeredByName, pin.x, pin.y - 27, 105);
        }
        context.restore();
    }

    function drawMinimap() {
        var minimap = document.getElementById('minimapCanvas');
        if (!minimap || document.getElementById('worldMinimap').classList.contains('is-collapsed')) {
            return;
        }
        var mapContext = minimap.getContext('2d');
        var scaleX = minimap.width / worldWidth;
        var scaleY = minimap.height / worldHeight;
        mapContext.clearRect(0, 0, minimap.width, minimap.height);
        mapContext.fillStyle = '#456f57';
        mapContext.fillRect(0, 0, minimap.width, minimap.height);
        mapContext.fillStyle = '#b89d70';
        mapContext.fillRect((building.x + building.width / 2 - 55) * scaleX, building.y + building.height * scaleY, 110 * scaleX, (worldHeight - building.y - building.height) * scaleY);
        mapContext.fillStyle = '#61666d';
        mapContext.fillRect(building.x * scaleX, building.y * scaleY, building.width * scaleX, building.height * scaleY);
        mapContext.fillStyle = '#d1c5a7';
        mapContext.fillRect((building.x + 18) * scaleX, (building.y + 18) * scaleY, (building.width - 36) * scaleX, (building.height - 36) * scaleY);
        mapContext.fillStyle = '#8d806d';
        mapContext.fillRect(825 * scaleX, 800 * scaleY, 1950 * scaleX, 240 * scaleY);
        mapContext.fillRect(1650 * scaleX, 1018 * scaleY, 300 * scaleX, 685 * scaleY);
        rooms.forEach(function (room) {
            mapContext.fillStyle = room.color;
            mapContext.fillRect(room.x * scaleX, room.y * scaleY, room.width * scaleX, room.height * scaleY);
            mapContext.strokeStyle = 'rgba(255,255,255,.8)';
            mapContext.lineWidth = 1;
            mapContext.strokeRect(room.x * scaleX, room.y * scaleY, room.width * scaleX, room.height * scaleY);
            mapContext.fillStyle = '#fff';
            mapContext.font = '5px system-ui, sans-serif';
            mapContext.textAlign = 'center';
            mapContext.fillText(room.title, (room.x + room.width / 2) * scaleX, (room.y + room.height / 2) * scaleY);
        });
        questionPins.forEach(function (pin) {
            mapContext.fillStyle = pin.answered || pin.locked ? '#56c58c' : '#c1adff';
            mapContext.beginPath();
            mapContext.arc(pin.x * scaleX, pin.y * scaleY, 2.4, 0, Math.PI * 2);
            mapContext.fill();
        });
        Object.keys(remotePlayers).forEach(function (key) {
            var other = remotePlayers[key];
            mapContext.fillStyle = other.is_same_group && !other.is_solo ? '#ffe28a' : '#e8eefc';
            mapContext.beginPath();
            mapContext.arc(other.x * scaleX, other.y * scaleY, 2.8, 0, Math.PI * 2);
            mapContext.fill();
        });
        mapContext.fillStyle = '#ff7d9d';
        mapContext.beginPath();
        mapContext.arc(player.x * scaleX, player.y * scaleY, 3.8, 0, Math.PI * 2);
        mapContext.fill();
    }

    function drawAvatar(x, y, avatar, direction, walkTime, label, isPlayer) {
        window.QuizAvatar.drawCharacter(
            context,
            x,
            y,
            avatar,
            direction,
            walkTime,
            label,
            { tone: isPlayer ? 'self' : 'other' }
        );
    }

    function locationAt(x, y) {
        for (var i = 0; i < rooms.length; i += 1) {
            var room = rooms[i];
            if (x > room.x && x < room.x + room.width && y > room.y && y < room.y + room.height) {
                return room.title;
            }
        }
        if (
            (x >= 825 && x <= 2775 && y >= 800 && y <= 1040) ||
            (x >= 1650 && x <= 1950 && y >= 1018 && y <= 1703)
        ) {
            return x >= 1650 && x <= 1950 && y > 1040 ? 'Lobi Gedung' : 'Koridor Gedung';
        }
        if (x >= building.x && x <= building.x + building.width && y >= building.y && y <= building.y + building.height) {
            return 'Gedung Kampus';
        }
        return 'Halaman Kampus';
    }

    function nearestQuestion() {
        var closest = null;
        var closestDistance = Infinity;
        questionPins.forEach(function (pin) {
            if (pin.answered || pin.locked) {
                return;
            }
            var dx = pin.x - player.x;
            var dy = pin.y - player.y;
            var distance = Math.sqrt(dx * dx + dy * dy);
            if (distance < closestDistance) {
                closest = pin;
                closestDistance = distance;
            }
        });
        return closestDistance <= 88 ? closest : null;
    }

    function showWorldNotice(message) {
        worldNotice = message;
        worldNoticeUntil = performance.now() + 4500;
        var hint = document.getElementById('worldHint');
        hint.textContent = message;
        hint.classList.add('is-visible');
        if (worldNoticeTimer) {
            window.clearTimeout(worldNoticeTimer);
        }
        worldNoticeTimer = window.setTimeout(function () {
            worldNotice = '';
            worldNoticeUntil = 0;
            updateHud();
        }, 4550);
    }

    function playTone(frequency, duration, waveform) {
        if (!soundEnabled) {
            return;
        }
        var AudioContextType = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextType) {
            return;
        }
        try {
            if (!audioContext) {
                audioContext = new AudioContextType();
            }
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            var oscillator = audioContext.createOscillator();
            var gain = audioContext.createGain();
            oscillator.type = waveform || 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(.055, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(.001, audioContext.currentTime + duration);
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start();
            oscillator.stop(audioContext.currentTime + duration);
        } catch (error) {
            console.warn('Efek suara tidak tersedia.', error);
        }
    }

    function updateTimer() {
        var remaining = Math.max(0, Math.ceil((sessionDeadlinePerformance - performance.now()) / 1000));
        var seconds = remaining;
        var minutes = Math.floor(seconds / 60);
        var secondsText = String(seconds % 60).padStart(2, '0');
        var timerText = minutes >= 60
            ? String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0') + ':' + secondsText
            : String(minutes).padStart(2, '0') + ':' + secondsText;
        document.getElementById('worldTimer').textContent = timerText;
        if (remaining === 0 && !sessionFinishRequested) {
            finishSession('Waktu Habis!');
        }
    }

    function finishSession(message) {
        if (sessionFinishRequested) {
            return;
        }
        sessionFinishRequested = true;
        if (activeQuestion) {
            activeQuestion.answered = true;
            activeQuestion = null;
        }
        isSubmitting = false;
        var questionDialog = document.getElementById('worldQuestionDialog');
        if (questionDialog.open) {
            questionDialog.close();
        }
        var formData = new FormData();
        formData.append('action', 'finish_session');
        formData.append('ajax', '1');
        formData.append('csrf_token', config.csrf);
        formData.append('sesi_kuis_id', String(config.sessionId));
        var completeDialog = document.getElementById('worldCompleteDialog');
        document.getElementById('worldCompleteTitle').textContent = message;
        document.getElementById('worldCompleteMessage').textContent = 'Menyimpan hasil sesi...';
        document.getElementById('worldResultLink').href = config.resultUrl;
        if (!completeDialog.open) {
            completeDialog.showModal();
        }

        function confirmFinish(attempt) {
            fetch(config.answerEndpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().catch(function () {
                    throw new Error('Server tidak mengirim konfirmasi penyelesaian yang valid.');
                }).then(function (payload) {
                    if (response.ok && payload.completed) {
                        return true;
                    }
                    if (response.status === 422 && attempt < 5) {
                        return false;
                    }
                    throw new Error(payload.error || 'Sesi belum dapat diselesaikan di server.');
                });
            }).then(function (finished) {
                if (!finished) {
                    window.setTimeout(function () {
                        confirmFinish(attempt + 1);
                    }, 500);
                    return;
                }
                document.getElementById('worldCompleteMessage').textContent =
                    'Sesi telah ditutup. Mengarahkan ke hasil permainan...';
                window.setTimeout(function () {
                    window.location.href = config.resultUrl;
                }, 900);
            }).catch(function (error) {
                console.error('Sesi belum dapat diselesaikan di server.', error);
                document.getElementById('worldCompleteMessage').textContent =
                    'Hasil belum terkonfirmasi. Coba muat ulang atau buka hasil setelah sesi tersimpan.';
                document.getElementById('worldResultLink').textContent = 'Coba buka hasil';
            });
        }

        confirmFinish(0);
    }

    function updateQuestionCountdown() {
        var now = performance.now();
        if (!activeQuestion) {
            var expiredPin = questionPins.find(function (pin) {
                return !pin.answered && pin.deadline && pin.deadline <= now;
            });
            if (expiredPin) {
                activeQuestion = expiredPin;
                submitAnswer(null, null, true);
            }
            return;
        }
        if (!activeQuestion.deadline) {
            return;
        }
        var remaining = Math.max(0, Math.ceil((activeQuestion.deadline - now) / 1000));
        var duration = activeQuestion.duration || 30;
        var countdown = document.getElementById('questionCountdown');
        document.getElementById('questionCountdownText').textContent =
            '00:' + String(remaining).padStart(2, '0');
        document.getElementById('questionCountdownBar').style.width =
            Math.max(0, remaining / duration * 100) + '%';
        countdown.classList.toggle('is-warning', remaining <= 10 && remaining > 5);
        countdown.classList.toggle('is-danger', remaining <= 5);
        if (remaining > 0 && remaining <= 10 && remaining !== activeQuestion.lastTick) {
            activeQuestion.lastTick = remaining;
            playTone(remaining <= 5 ? 500 : 680, .035, 'sine');
        }
        if (remaining === 0 && !isSubmitting) {
            submitAnswer(null, null, true);
        }
    }

    function updateHud() {
        var progress = config.total > 0 ? Math.round(config.answered / config.total * 100) : 0;
        document.getElementById('worldProgress').textContent = config.answered;
        document.getElementById('worldProgressBar').style.width = progress + '%';
        var nearby = nearestQuestion();
        var actionButton = document.getElementById('worldAction');
        var hint = document.getElementById('worldHint');
        actionButton.disabled = !nearby || Boolean(activeQuestion) || isSubmitting;
        actionButton.classList.toggle('is-ready', Boolean(nearby));
        if (worldNotice && performance.now() < worldNoticeUntil) {
            hint.textContent = worldNotice;
            hint.classList.add('is-visible');
        } else if (nearby) {
            hint.textContent = 'Tekan Aksi atau Spasi untuk membuka soal ' + nearby.order;
            hint.classList.add('is-visible');
        } else {
            hint.textContent = 'Dekati Pos Soal lalu tekan Aksi / Spasi';
            hint.classList.remove('is-visible');
        }
        document.getElementById('worldLocation').textContent = locationAt(player.x, player.y);
    }

    function drawWorld(time) {
        var cameraX = Math.max(0, Math.min(worldWidth - width, player.x - width / 2));
        var cameraY = Math.max(0, Math.min(worldHeight - height, player.y - height / 2));
        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        context.clearRect(0, 0, width, height);
        context.save();
        context.translate(-cameraX, -cameraY);
        drawGrass(cameraX, cameraY);
        trees.forEach(function (tree, index) { drawTree(tree[0], tree[1], index); });
        benchPositions.forEach(function (bench) { drawBench(bench[0], bench[1]); });
        drawCampusBuilding();
        rooms.forEach(drawRoom);
        questionPins.forEach(function (pin) { drawPin(pin, time); });

        Object.keys(remotePlayers).forEach(function (key) {
            var other = remotePlayers[key];
            var dx = other.targetX - other.x;
            var dy = other.targetY - other.y;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var maxStep = 240 * frameDelta;
            var step = Math.min(1, maxStep / Math.max(distance, 0.001));
            other.x += dx * step;
            other.y += dy * step;
            other.lastFrame = time;
            if (distance > 1.5) {
                other.walkTime += frameDelta;
                other.walkFrame = time;
                if (Math.abs(dx) > Math.abs(dy)) {
                    other.direction = dx < 0 ? 'left' : 'right';
                } else {
                    other.direction = dy < 0 ? 'up' : 'down';
                }
            } else {
                other.walkTime = 0;
                other.walkFrame = time;
            }
            var isSolo = other.is_solo;
            var isTeam = other.is_same_group && !isSolo;
            var subtitle = isSolo
                ? 'Solo'
                : (isTeam ? '♧ ' + (other.nama_kelompok || 'Teman Kelompok') : (other.nama_kelompok || 'Pemain lain'));
            window.QuizAvatar.drawCharacter(
                context,
                other.x,
                other.y,
                other.avatar_config,
                other.direction,
                other.walkTime,
                other.name,
                { tone: isSolo ? 'solo' : (isTeam ? 'team' : 'other'), subtitle: subtitle }
            );
        });
        drawAvatar(player.x, player.y, config.avatar || {}, player.direction, player.walkTime, config.playerName, true);
        context.restore();
        drawMinimap();
    }

    function updateDoors(delta) {
        rooms.forEach(function (room) {
            var doorX = room.x + room.width / 2;
            var doorY = room.doorSide === 'north' ? room.y + 8 : room.y + room.height - 8;
            var occupied = player.x > room.x + 16 && player.x < room.x + room.width - 16
                && player.y > room.y + 16 && player.y < room.y + room.height - 16;
            Object.keys(remotePlayers).forEach(function (key) {
                var other = remotePlayers[key];
                var otherInside = other.x > room.x + 16 && other.x < room.x + room.width - 16
                    && other.y > room.y + 16 && other.y < room.y + room.height - 16;
                var nearDoorX = other.x - doorX;
                var nearDoorY = other.y - doorY;
                if (otherInside || nearDoorX * nearDoorX + nearDoorY * nearDoorY <= 84 * 84) {
                    occupied = true;
                }
            });
            var dx = player.x - doorX;
            var dy = player.y - doorY;
            var nearDoor = dx * dx + dy * dy <= 84 * 84;
            var target = occupied || nearDoor ? 1 : 0;
            var step = Math.min(1, delta * 3.8);
            room.doorOpen += (target - room.doorOpen) * step;
            if (Math.abs(target - room.doorOpen) < .01) {
                room.doorOpen = target;
            }
        });
        var mainDoorY = building.y + building.height - 8;
        var mainDoorNearby = Math.abs(player.x - mainDoor.x) < mainDoor.width / 2 + 72
            && Math.abs(player.y - mainDoorY) < 112;
        var mainDoorOccupied = player.x > mainDoor.x - 150 && player.x < mainDoor.x + 150
            && player.y > building.y + building.height - 140 && player.y < building.y + building.height + 110;
        Object.keys(remotePlayers).forEach(function (key) {
            var other = remotePlayers[key];
            var near = Math.abs(other.x - mainDoor.x) < mainDoor.width / 2 + 72
                && Math.abs(other.y - mainDoorY) < 112;
            var inside = other.x > mainDoor.x - 150 && other.x < mainDoor.x + 150
                && other.y > building.y + building.height - 140 && other.y < building.y + building.height + 110;
            mainDoorNearby = mainDoorNearby || near;
            mainDoorOccupied = mainDoorOccupied || inside;
        });
        var mainTarget = mainDoorNearby || mainDoorOccupied ? 1 : 0;
        mainDoor.open += (mainTarget - mainDoor.open) * Math.min(1, delta * 3.8);
        if (Math.abs(mainTarget - mainDoor.open) < .01) {
            mainDoor.open = mainTarget;
        }
    }

    function movePlayer(delta) {
        var horizontal = controls.x + (keys.arrowright || keys.d ? 1 : 0) - (keys.arrowleft || keys.a ? 1 : 0);
        var vertical = controls.y + (keys.arrowdown || keys.s ? 1 : 0) - (keys.arrowup || keys.w ? 1 : 0);
        var magnitude = Math.sqrt(horizontal * horizontal + vertical * vertical);
        if (magnitude > 1) {
            horizontal /= magnitude;
            vertical /= magnitude;
        }
        if (Math.abs(horizontal) > Math.abs(vertical) && horizontal !== 0) {
            player.direction = horizontal < 0 ? 'left' : 'right';
        } else if (vertical !== 0) {
            player.direction = vertical < 0 ? 'up' : 'down';
        }
        if (magnitude > .12 && !activeQuestion) {
            var speed = 210 * delta;
            var nextX = player.x + horizontal * speed;
            var nextY = player.y + vertical * speed;
            if (!collidesAt(nextX, player.y)) {
                player.x = nextX;
            }
            if (!collidesAt(player.x, nextY)) {
                player.y = nextY;
            }
            player.walkTime += delta;
        } else {
            player.walkTime = 0;
        }
    }

    function frame(time) {
        var delta = Math.min(.04, lastFrame ? (time - lastFrame) / 1000 : 0);
        frameDelta = delta;
        lastFrame = time;
        updateDoors(delta);
        movePlayer(delta);
        drawWorld(time);
        updateHud();
        updateTimer();
        updateQuestionCountdown();
        window.requestAnimationFrame(frame);
    }

    function categoryFrom(question) {
        var match = question.match(/^\[([^\]]{1,60})\]\s*/);
        return match ? { category: match[1], text: question.replace(/^\[[^\]]{1,60}\]\s*/, '') } : { category: 'Pengetahuan Umum', text: question };
    }

    function openQuestion(pin) {
        if (!pin || activeQuestion || isSubmitting || pin.answered || pin.locked) {
            return;
        }
        activeQuestion = pin;
        pin.duration = pin.type === 'esai' ? 45 : 30;
        pin.deadline = 0;
        pin.lastTick = null;
        playTone(680, .09, 'triangle');
        var parsed = categoryFrom(pin.question);
        var dialog = document.getElementById('worldQuestionDialog');
        document.getElementById('worldQuestionCategory').textContent = parsed.category;
        document.getElementById('worldQuestionType').textContent = pin.type === 'esai' ? 'ESAI' : 'PILIHAN GANDA';
        document.getElementById('worldQuestionOrder').textContent = 'SOAL ' + String(pin.order).padStart(2, '0');
        document.getElementById('worldQuestionTitle').textContent = parsed.text;
        document.getElementById('worldAnswerFeedback').textContent = '';
        var options = document.getElementById('worldAnswerOptions');
        var essayForm = document.getElementById('worldEssayForm');
        var essayInput = document.getElementById('worldEssayAnswer');
        options.replaceChildren();
        options.disabled = false;
        essayForm.hidden = pin.type !== 'esai';
        options.hidden = pin.type === 'esai';
        essayInput.value = '';
        essayInput.disabled = false;
        essayForm.querySelector('[type="submit"]').disabled = false;
        if (pin.type === 'esai') {
            essayInput.required = true;
        } else {
            essayInput.required = false;
            Object.keys(pin.options).forEach(function (letter) {
                var button = document.createElement('button');
                var badge = document.createElement('span');
                var text = document.createElement('span');
                button.type = 'button';
                button.className = 'world-option-button';
                button.dataset.answer = letter;
                badge.className = 'world-option-letter';
                badge.textContent = letter;
                text.className = 'world-option-text';
                text.textContent = pin.options[letter];
                button.appendChild(badge);
                button.appendChild(text);
                button.addEventListener('click', function () { submitAnswer(letter, null); });
                options.appendChild(button);
            });
        }
        dialog.showModal();
        var timerRequest = new FormData();
        timerRequest.append('action', 'question_timer');
        timerRequest.append('ajax', '1');
        timerRequest.append('csrf_token', config.csrf);
        timerRequest.append('sesi_kuis_id', String(config.sessionId));
        timerRequest.append('soal_id', String(pin.id));
        fetch(config.answerEndpoint, {
            method: 'POST',
            body: timerRequest,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.error || 'Timer soal tidak dapat dimulai.');
                }
                return payload;
            });
        }).then(function (payload) {
            if (payload.answered) {
                pin.answered = true;
                pin.answeredByName = payload.answered_by_name || '';
                document.getElementById('worldAnswerFeedback').textContent =
                    (pin.answeredByName || 'Teman tim') + ' baru saja menyelesaikan soal ini.';
                window.setTimeout(function () {
                    if (dialog.open && activeQuestion === pin) {
                        dialog.close();
                    }
                }, 1100);
                return;
            }
            pin.duration = Number(payload.duration) || pin.duration;
            pin.deadline = performance.now() + Math.max(0, Number(payload.remaining) || 0) * 1000;
        }).catch(function (error) {
            document.getElementById('worldAnswerFeedback').textContent = error.message;
            document.getElementById('worldAnswerFeedback').classList.add('is-wrong');
        });
        if (pin.type === 'esai') {
            window.setTimeout(function () { essayInput.focus(); }, 40);
        }
    }

    function submitAnswer(choice, textAnswer, timedOut) {
        if (!activeQuestion || activeQuestion.answered || isSubmitting || (timedOut && activeQuestion.timeoutRequested)) {
            return;
        }
        var question = activeQuestion;
        if (timedOut) {
            question.timeoutRequested = true;
            question.locked = true;
            question.deadline = 0;
            question.answeredByName = config.playerName;
        }
        isSubmitting = true;
        var feedback = document.getElementById('worldAnswerFeedback');
        var options = document.getElementById('worldAnswerOptions');
        var essayForm = document.getElementById('worldEssayForm');
        var essayInput = document.getElementById('worldEssayAnswer');
        options.querySelectorAll('.world-option-button').forEach(function (button) { button.disabled = true; });
        essayForm.querySelector('[type="submit"]').disabled = true;
        essayInput.disabled = true;
        feedback.textContent = timedOut ? 'Waktu soal habis!' : 'Menyimpan jawaban...';
        feedback.className = 'world-answer-feedback';
        if (timedOut && !question.autoCloseTimer) {
            question.autoCloseTimer = window.setTimeout(function () {
                var dialog = document.getElementById('worldQuestionDialog');
                if (dialog.open && activeQuestion === question) {
                    dialog.close();
                }
            }, 1300);
        }

        var formData = new FormData();
        formData.append('action', 'answer');
        formData.append('ajax', '1');
        formData.append('csrf_token', config.csrf);
        formData.append('sesi_kuis_id', String(config.sessionId));
        formData.append('soal_id', String(question.id));
        if (timedOut) {
            formData.append('timeout', '1');
        } else if (choice) {
            formData.append('jawaban', choice);
        } else {
            formData.append('jawaban_teks', textAnswer || '');
        }

        var controller = new AbortController();
        var requestTimeout = window.setTimeout(function () {
            controller.abort();
        }, 8000);
        fetch(config.answerEndpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Server tidak mengirim respons jawaban yang valid. Silakan coba lagi.');
            }).then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.error || 'Jawaban belum dapat disimpan.');
                }
                return payload;
            });
        }).then(function (payload) {
            question.answered = true;
            question.answeredByName = payload.answered_by_name || config.playerName;
            question.deadline = 0;
            config.answered = payload.answered;
            if (payload.shared_score !== undefined) {
                config.players.forEach(function (participant) {
                    participant.score = Number(payload.shared_score) || 0;
                });
            }
            config.players = config.players.map(function (participant) {
                if (participant.id === Number(config.playerId)) {
                    if (payload.shared_score === undefined) {
                        participant.score += payload.score;
                    }
                    participant.name = config.playerName;
                    participant.answered = payload.answered;
                    participant.total = payload.total;
                    if (payload.completed) {
                        participant.status = 'selesai';
                    }
                }
                return participant;
            });
            feedback.textContent = payload.duplicate
                ? (payload.answered_by_name || 'Teman tim') + ' baru saja menyelesaikan soal ini.'
                : (timedOut
                    ? 'Waktu soal habis!'
                    : (payload.correct ? '✓ Benar! +' + payload.score + ' poin' : 'Belum tepat. Jawaban sudah tersimpan.'));
            feedback.classList.add(payload.correct ? 'is-correct' : 'is-wrong');
            playTone(payload.correct ? 820 : 230, payload.correct ? .2 : .25, payload.correct ? 'triangle' : 'sawtooth');
            renderPlayerLists();
            if (payload.completed || (config.total > 0 && config.answered >= config.total)) {
                finishSession('Sesi Selesai!');
                return;
            }
            window.setTimeout(function () {
                var dialog = document.getElementById('worldQuestionDialog');
                if (dialog.open && activeQuestion === question) {
                    dialog.close();
                }
                if (payload.completed) {
                    document.getElementById('worldResultLink').href = payload.result_url || config.resultUrl;
                    document.getElementById('worldCompleteDialog').showModal();
                }
            }, 1050);
        }).catch(function (error) {
            var message = error.name === 'AbortError'
                ? 'Koneksi terlalu lama. Periksa jaringan sebelum mencoba lagi.'
                : error.message;
            feedback.textContent = message;
            feedback.classList.add('is-wrong');
            if (timedOut) {
                showWorldNotice('Waktu soal habis, tetapi server belum mengonfirmasi penyimpanan.');
            }
        }).finally(function () {
            window.clearTimeout(requestTimeout);
            isSubmitting = false;
            options.querySelectorAll('.world-option-button').forEach(function (button) {
                button.disabled = question.answered || question.locked;
            });
            essayForm.querySelector('[type="submit"]').disabled = question.answered || question.locked;
            essayInput.disabled = question.answered || question.locked;
            if (feedback.textContent === 'Menyimpan jawaban...') {
                feedback.textContent = 'Jawaban belum dikonfirmasi. Periksa koneksi lalu coba lagi.';
                feedback.classList.add('is-wrong');
            }
            updateHud();
        });
    }

    function remotePlayerKey(participant) {
        return participant.session_id + ':' + participant.id;
    }

    function positionFormData(action) {
        var data = new FormData();
        data.append('action', action);
        data.append('csrf_token', config.csrf);
        data.append('sesi_id', String(config.sessionId));
        if (action === 'sync') {
            data.append('pos_x', String(Math.round(player.x)));
            data.append('pos_y', String(Math.round(player.y)));
            data.append('arah', player.direction);
        }
        return data;
    }

    function synchronizePosition() {
        if (document.hidden || syncInFlight) {
            return;
        }
        syncInFlight = true;
        fetch(config.positionEndpoint, {
            method: 'POST',
            body: positionFormData('sync'),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || !Array.isArray(payload.players)) {
                return;
            }
            if (payload.session_expired) {
                finishSession('Waktu Habis!');
                return;
            }
            if (Array.isArray(payload.answered_questions)) {
                payload.answered_questions.forEach(function (answer) {
                    var pin = questionPins.find(function (question) {
                        return Number(question.id) === Number(answer.soal_id);
                    });
                    if (pin) {
                        pin.answered = true;
                        pin.answeredByName = answer.name || '';
                    }
                });
                config.answered = questionPins.filter(function (question) {
                    return question.answered;
                }).length;
                config.players.forEach(function (participant) {
                    participant.answered = config.answered;
                    if (config.sharedProgress && payload.shared_score !== undefined) {
                        participant.score = Number(payload.shared_score) || 0;
                    }
                });
            }
            var activeKeys = Object.create(null);
            payload.players.forEach(function (item) {
                item.id = Number(item.id);
                item.session_id = Number(item.session_id);
                item.pos_x = Number(item.pos_x);
                item.pos_y = Number(item.pos_y);
                item.answered = Number(item.answered) || 0;
                item.total = Number(item.total) || 0;
                item.score = Number(item.score) || 0;
                item.personal_score = Number(item.personal_score) || 0;
                item.avatar_config = item.avatar_config || {};
                var key = remotePlayerKey(item);
                activeKeys[key] = true;
                var current = remotePlayers[key];
                if (!current) {
                    item.x = item.pos_x;
                    item.y = item.pos_y;
                    item.targetX = item.pos_x;
                    item.targetY = item.pos_y;
                    item.direction = item.arah || 'down';
                    item.walkTime = 0;
                    item.walkFrame = performance.now();
                    item.lastFrame = performance.now();
                    remotePlayers[key] = item;
                } else {
                    current.targetX = item.pos_x;
                    current.targetY = item.pos_y;
                    current.arah = item.arah;
                    current.name = item.name;
                    current.avatar_config = item.avatar_config;
                    current.nama_kelompok = item.nama_kelompok;
                    current.group_id = item.group_id;
                    current.is_same_group = item.is_same_group;
                    current.is_solo = item.is_solo;
                    current.answered = item.answered;
                    current.total = item.total;
                    current.score = item.score;
                    current.personal_score = item.personal_score;
                }
                if (item.session_id === Number(config.sessionId)) {
                    config.players.forEach(function (participant) {
                        if (participant.id === item.id) {
                            participant.name = item.name;
                            participant.answered = item.answered;
                            participant.total = item.total;
                            participant.score = item.score;
                            participant.personal_score = item.personal_score;
                            participant.status = item.total > 0 && item.answered >= item.total ? 'selesai' : 'berlangsung';
                        }
                    });
                }
            });
            Object.keys(remotePlayers).forEach(function (key) {
                if (!activeKeys[key]) {
                    delete remotePlayers[key];
                }
            });
            renderPlayerLists();
        }).catch(function () {
            // Temporary network failures should not interrupt the quiz.
        }).finally(function () {
            syncInFlight = false;
            var moving = !activeQuestion && (
                Math.abs(controls.x) > .12 ||
                Math.abs(controls.y) > .12 ||
                keys.arrowup || keys.arrowdown || keys.arrowleft || keys.arrowright ||
                keys.w || keys.a || keys.s || keys.d
            );
            window.setTimeout(synchronizePosition, moving ? 450 : 900);
        });
    }

    function leavePosition() {
        if (!config.positionEndpoint) {
            return;
        }
        try {
            var data = positionFormData('leave');
            if (navigator.sendBeacon) {
                navigator.sendBeacon(config.positionEndpoint, data);
            } else {
                fetch(config.positionEndpoint, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true
                }).catch(function () {});
            }
        } catch (error) {
            // The browser may reject a beacon during shutdown.
        }
    }

    function addPlayerSectionTitle(parent, title) {
        var heading = document.createElement('h3');
        heading.className = 'world-list-section-title';
        heading.textContent = title;
        parent.appendChild(heading);
    }

    function appendPlayerRow(parent, nameText, progressText, statusText, type) {
        var row = document.createElement('div');
        var name = document.createElement('strong');
        var status = document.createElement('span');
        var progress = document.createElement('small');
        row.className = 'world-player-row' + (type ? ' is-' + type : '');
        name.textContent = nameText;
        progress.className = 'world-player-progress';
        progress.textContent = progressText;
        status.className = 'world-player-status';
        status.textContent = statusText;
        row.appendChild(name);
        row.appendChild(progress);
        row.appendChild(status);
        parent.appendChild(row);
    }

    function renderPlayerLists() {
        var playersList = document.getElementById('worldPlayersList');
        var rankingList = document.getElementById('worldRankingList');
        var playersScrollTop = playersList.scrollTop;
        var rankingScrollTop = rankingList.scrollTop;
        playersList.replaceChildren();
        rankingList.replaceChildren();
        var seenPlayers = Object.create(null);
        if (config.groupId !== null) {
            addPlayerSectionTitle(playersList, 'Teman Kelompok');
        } else {
            addPlayerSectionTitle(playersList, 'Mode Individu (Solo)');
        }
        config.players.forEach(function (participant) {
            var isSelf = participant.id === Number(config.playerId);
            seenPlayers[String(participant.id)] = true;
            appendPlayerRow(
                playersList,
                participant.name + (isSelf ? ' (Kamu)' : ''),
                'Soal ' + (participant.answered || (isSelf ? config.answered : 0)) + '/' + (participant.total || config.total),
                participant.status === 'selesai' ? 'Selesai' : 'Bermain',
                isSelf ? 'self' : 'team'
            );
        });

        if (config.groupId !== null) {
            Object.keys(remotePlayers).forEach(function (key) {
                var participant = remotePlayers[key];
                if (participant.is_same_group && !participant.is_solo && !seenPlayers[String(participant.id)]) {
                    seenPlayers[String(participant.id)] = true;
                    appendPlayerRow(
                        playersList,
                        participant.name,
                        'Soal ' + participant.answered + '/' + participant.total,
                        participant.answered >= participant.total && participant.total > 0 ? 'Selesai' : 'Bermain',
                        'team'
                    );
                }
            });
        }

        addPlayerSectionTitle(playersList, 'Pemain Lain di Peta');
        var otherCount = 0;
        Object.keys(remotePlayers).forEach(function (key) {
            var participant = remotePlayers[key];
            if (!participant.is_same_group || participant.is_solo) {
                otherCount += 1;
                var suffix = participant.is_solo ? ' · Solo' : ' · ' + (participant.nama_kelompok || 'Kelompok lain');
                appendPlayerRow(
                    playersList,
                    participant.name + suffix,
                    'Soal ' + participant.answered + '/' + participant.total,
                    'Di peta',
                    participant.is_solo ? 'solo' : 'other'
                );
            }
        });
        if (otherCount === 0) {
            appendPlayerRow(playersList, 'Belum ada pemain lain yang aktif', '', '', 'other');
        }

        var activeEntries = config.players.map(function (participant) {
            return {
                id: Number(participant.id),
                session_id: Number(config.sessionId),
                name: participant.name,
                group_id: participant.group_id === undefined ? config.groupId : participant.group_id,
                group_name: participant.group_name || config.groupName,
                is_solo: config.groupId === null,
                score: Number(participant.score) || 0,
                personal_score: Number(participant.personal_score) || 0,
                answered: Number(participant.answered) || 0,
                total: Number(participant.total) || config.total
            };
        });
        var activeEntryKeys = Object.create(null);
        activeEntries.forEach(function (participant) {
            activeEntryKeys[participant.session_id + ':' + participant.id] = true;
        });
        Object.keys(remotePlayers).forEach(function (key) {
            var participant = remotePlayers[key];
            var participantKey = Number(participant.session_id) + ':' + Number(participant.id);
            if (activeEntryKeys[participantKey]) {
                return;
            }
            activeEntryKeys[participantKey] = true;
            activeEntries.push({
                id: Number(participant.id),
                session_id: Number(participant.session_id),
                name: participant.name,
                group_id: participant.group_id,
                group_name: participant.nama_kelompok,
                is_solo: Boolean(participant.is_solo),
                score: Number(participant.score) || 0,
                personal_score: Number(participant.personal_score) || 0,
                answered: Number(participant.answered) || 0,
                total: Number(participant.total) || 0
            });
        });

        var groupScores = Object.create(null);
        activeEntries.forEach(function (participant) {
            if (participant.group_id === null || participant.is_solo) {
                return;
            }
            var groupKey = String(participant.group_id);
            if (!groupScores[groupKey] || participant.score > groupScores[groupKey].score) {
                groupScores[groupKey] = {
                    id: Number(participant.group_id),
                    name: participant.group_name || 'Kelompok',
                    score: participant.score,
                    answered: participant.answered,
                    total: participant.total
                };
            }
        });
        var rankingEntries;
        if (config.rankingMode === 'individual') {
            rankingEntries = activeEntries.slice().sort(function (left, right) {
                return right.personal_score - left.personal_score;
            });
            addPlayerSectionTitle(rankingList, 'Peringkat Individu & Solo · live');
        } else {
            rankingEntries = Object.keys(groupScores).map(function (key) { return groupScores[key]; })
                .sort(function (left, right) { return right.score - left.score; });
            addPlayerSectionTitle(rankingList, 'Peringkat Antar Kelompok · live');
        }
        rankingEntries.forEach(function (participant, index) {
            var row = document.createElement('div');
            var rank = document.createElement('span');
            var name = document.createElement('strong');
            var score = document.createElement('b');
            row.className = 'world-ranking-row';
            rank.className = 'world-ranking-number';
            rank.textContent = ['🥇', '🥈', '🥉'][index] || String(index + 1).padStart(2, '0');
            if (config.rankingMode === 'individual') {
                name.textContent = participant.name +
                    (participant.id === Number(config.playerId) ? ' (Kamu)' : '') +
                    (participant.is_solo ? ' · Solo' : ' · ' + (participant.group_name || 'Kelompok'));
                score.textContent = participant.personal_score + ' poin';
            } else {
                name.textContent = participant.name +
                    (Number(participant.id) === Number(config.groupId) ? ' (Kelompokmu)' : '');
                score.textContent = participant.score + ' poin';
            }
            row.appendChild(rank);
            row.appendChild(name);
            row.appendChild(score);
            rankingList.appendChild(row);
        });
        if (rankingEntries.length === 0) {
            appendPlayerRow(rankingList, 'Belum ada kelompok aktif', '', '', 'other');
        }
        playersList.scrollTop = playersScrollTop;
        rankingList.scrollTop = rankingScrollTop;
    }

    function closeDialog(button) {
        var dialog = button.closest('dialog');
        if (dialog && dialog !== document.getElementById('worldCompleteDialog')) {
            dialog.close();
            if (dialog.id === 'worldQuestionDialog') {
                activeQuestion = null;
            }
        }
    }

    document.addEventListener('keydown', function (event) {
        var target = event.target;
        var isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
        var isInteractive = target && (target.tagName === 'BUTTON' || target.tagName === 'A' || target.closest('button, a'));
        if (event.key === 'Escape') {
            var openDialog = document.querySelector('dialog[open]');
            if (openDialog && openDialog.id !== 'worldCompleteDialog') {
                openDialog.close();
                if (openDialog.id === 'worldQuestionDialog') {
                    activeQuestion = null;
                }
            }
            return;
        }
        if (isTyping || isInteractive || document.querySelector('dialog[open]')) {
            return;
        }
        var key = event.key.toLowerCase();
        if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright', ' ', 'w', 'a', 's', 'd'].indexOf(key) !== -1) {
            event.preventDefault();
        }
        if (key === ' ' || key === 'enter') {
            if (!activeQuestion) {
                openQuestion(nearestQuestion());
            }
            return;
        }
        keys[key] = true;
    });
    document.addEventListener('keyup', function (event) {
        keys[event.key.toLowerCase()] = false;
    });
    window.addEventListener('blur', function () {
        keys = Object.create(null);
        controls.x = 0;
        controls.y = 0;
    });

    var joystick = document.getElementById('worldJoystick');
    var knob = document.getElementById('joystickKnob');
    var joystickPointer = null;
    function updateJoystick(event) {
        var bounds = joystick.getBoundingClientRect();
        var centerX = bounds.left + bounds.width / 2;
        var centerY = bounds.top + bounds.height / 2;
        var dx = event.clientX - centerX;
        var dy = event.clientY - centerY;
        var maximum = bounds.width * .32;
        var distance = Math.sqrt(dx * dx + dy * dy);
        if (distance > maximum) {
            dx = dx / distance * maximum;
            dy = dy / distance * maximum;
        }
        controls.x = dx / maximum;
        controls.y = dy / maximum;
        knob.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
    }
    joystick.addEventListener('pointerdown', function (event) {
        event.preventDefault();
        joystickPointer = event.pointerId;
        joystick.setPointerCapture(event.pointerId);
        updateJoystick(event);
    });
    joystick.addEventListener('pointermove', function (event) {
        if (event.pointerId === joystickPointer) {
            event.preventDefault();
            updateJoystick(event);
        }
    });
    function resetJoystick(event) {
        if (event.pointerId === joystickPointer) {
            joystickPointer = null;
            controls.x = 0;
            controls.y = 0;
            knob.style.transform = 'translate(0, 0)';
        }
    }
    joystick.addEventListener('pointerup', resetJoystick);
    joystick.addEventListener('pointercancel', resetJoystick);

    document.getElementById('worldAction').addEventListener('click', function () {
        openQuestion(nearestQuestion());
    });
    document.getElementById('toggleMinimap').addEventListener('click', function () {
        var minimap = document.getElementById('worldMinimap');
        var collapsed = minimap.classList.toggle('is-collapsed');
        this.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        this.querySelector('span').textContent = collapsed ? '+' : '−';
    });
    document.getElementById('worldSound').addEventListener('click', function () {
        soundEnabled = !soundEnabled;
        this.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
        this.innerHTML = soundEnabled ? '<span>♫</span> Suara ON' : '<span>♪</span> Suara OFF';
        if (soundEnabled) {
            playTone(580, .1, 'triangle');
        }
    });
    document.getElementById('openPlayers').addEventListener('click', function () {
        renderPlayerLists();
        document.getElementById('worldPlayersDialog').showModal();
    });
    document.getElementById('openRanking').addEventListener('click', function () {
        config.rankingMode = config.rankingMode || 'groups';
        renderPlayerLists();
        document.getElementById('worldRankingDialog').showModal();
    });
    document.querySelectorAll('[data-ranking-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            config.rankingMode = button.dataset.rankingMode;
            document.querySelectorAll('[data-ranking-mode]').forEach(function (tab) {
                var selected = tab === button;
                tab.classList.toggle('is-selected', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            renderPlayerLists();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
        button.addEventListener('click', function () { closeDialog(button); });
    });
    document.querySelectorAll('dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
                if (dialog.id === 'worldQuestionDialog') {
                    activeQuestion = null;
                }
            }
        });
    });
    document.getElementById('worldQuestionDialog').addEventListener('close', function () {
        if (activeQuestion) {
            activeQuestion = null;
        }
    });
    window.addEventListener('quiz-world:avatar-updated', function (event) {
        var profile = event.detail;
        if (
            !profile ||
            typeof profile.nama_panggilan !== 'string' ||
            !profile.avatar_config ||
            typeof profile.avatar_config !== 'object'
        ) {
            return;
        }

        config.playerName = profile.nama_panggilan;
        config.avatar = profile.avatar_config;
        config.players.forEach(function (participant) {
            if (participant.id === Number(config.playerId)) {
                participant.name = config.playerName;
            }
        });
        renderPlayerLists();
    });
    document.getElementById('worldEssayForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var answer = document.getElementById('worldEssayAnswer').value.trim();
        if (answer !== '') {
            submitAnswer(null, answer);
        }
    });

    window.addEventListener('resize', resizeCanvas);
    window.addEventListener('pagehide', leavePosition);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            synchronizePosition();
        }
    });
    config.playerId = Number(config.playerId || 0);
    config.players.forEach(function (participant) {
        participant.avatar = participant.avatar || {};
    });
    benchPositions.forEach(function (bench) {
        addFurnitureRect(bench[0], bench[1], 62, 24);
    });
    createFurnitureObstacles();
    createPins();
    resizeCanvas();
    renderPlayerLists();
    synchronizePosition();
    window.requestAnimationFrame(frame);
}());
