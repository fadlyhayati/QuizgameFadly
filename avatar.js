(function () {
    function roundedRect(context, x, y, width, height, radius) {
        var r = Math.min(radius, width / 2, height / 2);
        context.beginPath();
        context.moveTo(x + r, y);
        context.arcTo(x + width, y, x + width, y + height, r);
        context.arcTo(x + width, y + height, x, y + height, r);
        context.arcTo(x, y + height, x, y, r);
        context.arcTo(x, y, x + width, y, r);
        context.closePath();
    }

    window.QuizAvatar = window.QuizAvatar || {};
    window.QuizAvatar.drawCharacter = function (context, x, y, avatar, direction, walkTime, label, presentation) {
        avatar = avatar || {};
        presentation = presentation || {};
        var skin = avatar.skin || '#f2c6a0';
        var hair = avatar.hair_color || '#38234f';
        var shirt = avatar.shirt_color || '#a78bfa';
        var pants = avatar.pants_color || '#5266b8';
        var style = avatar.hair_style || 'short';
        var clothes = avatar.shirt_style || 'tshirt';
        var accessory = avatar.accessory || 'none';
        var facing = direction === 'front' ? 'down' : (direction === 'back' ? 'up' : (direction === 'side' ? 'right' : direction));
        var isMoving = Number(walkTime) > 0;
        var bob = isMoving ? Math.sin(walkTime * 12) * 1.2 : 0;
        var legSwing = isMoving ? Math.sin(walkTime * 14) * 4.5 : 0;

        context.save();
        context.translate(x, y);
        context.fillStyle = 'rgba(17, 26, 27, .28)';
        context.beginPath();
        context.ellipse(0, 1, 15, 5, 0, 0, Math.PI * 2);
        context.fill();
        context.translate(0, bob);

        if (style === 'long' || style === 'bob') {
            context.fillStyle = hair;
            context.beginPath();
            context.moveTo(-12, -53);
            context.quadraticCurveTo(-18, -69, 0, -69);
            context.quadraticCurveTo(18, -69, 12, -52);
            context.lineTo(15, style === 'bob' ? -29 : -20);
            context.quadraticCurveTo(12, style === 'bob' ? -25 : -17, 7, style === 'bob' ? -29 : -20);
            context.lineTo(6, -49);
            context.lineTo(-6, -49);
            context.lineTo(-7, style === 'bob' ? -29 : -20);
            context.quadraticCurveTo(-12, style === 'bob' ? -25 : -17, -15, style === 'bob' ? -29 : -20);
            context.closePath();
            context.fill();
        } else if (style === 'pony') {
            context.fillStyle = hair;
            context.beginPath();
            context.moveTo(7, -60);
            context.quadraticCurveTo(17, -57, 17, -49);
            context.quadraticCurveTo(17, -40, 23, -35);
            context.quadraticCurveTo(29, -29, 22, -24);
            context.quadraticCurveTo(14, -28, 14, -38);
            context.quadraticCurveTo(12, -47, 7, -49);
            context.closePath();
            context.fill();
            context.fillStyle = '#e6c45f';
            context.beginPath();
            context.ellipse(16, -49, 3.2, 2.2, -.3, 0, Math.PI * 2);
            context.fill();
        } else if (style === 'curly') {
            context.fillStyle = hair;
            [-10, -5, 1, 7, 12].forEach(function (offset, index) {
                context.beginPath();
                context.arc(offset, -64 - (index % 2) * 3, 7, 0, Math.PI * 2);
                context.fill();
            });
        }

        function drawLeg(offset, swing) {
            context.save();
            context.translate(offset, -18);
            context.rotate(swing * .035);
            context.fillStyle = pants;
            roundedRect(context, -4.5, 0, 9, 15, 3);
            context.fill();
            context.fillStyle = '#eee9df';
            roundedRect(context, -5, 11, 9, 5, 2);
            context.fill();
            context.restore();
        }
        drawLeg(-5, legSwing);
        drawLeg(5, -legSwing);

        if (style !== 'hijab') {
            context.fillStyle = skin;
            context.beginPath();
            context.moveTo(-13, -36);
            context.quadraticCurveTo(-18, -39, -18, -33);
            context.lineTo(-17, -22);
            context.quadraticCurveTo(-16, -18, -12, -20);
            context.lineTo(-10, -30);
            context.closePath();
            context.fill();
            context.beginPath();
            context.moveTo(13, -36);
            context.quadraticCurveTo(18, -39, 18, -33);
            context.lineTo(17, -22);
            context.quadraticCurveTo(16, -18, 12, -20);
            context.lineTo(10, -30);
            context.closePath();
            context.fill();
        }

        if (clothes === 'hoodie') {
            context.fillStyle = shirt;
            context.beginPath();
            context.arc(0, -36, 11, Math.PI, 0);
            context.lineTo(11, -27);
            context.lineTo(-11, -27);
            context.closePath();
            context.fill();
        }
        context.fillStyle = shirt;
        roundedRect(context, -12, -39, 24, 22, clothes === 'tshirt' ? 6 : 4);
        context.fill();
        if (clothes === 'tshirt' || clothes === 'shirt') {
            roundedRect(context, -17, -37, 7, 12, 3);
            context.fill();
            roundedRect(context, 10, -37, 7, 12, 3);
            context.fill();
        } else {
            roundedRect(context, -17, -38, 7, 19, 3);
            context.fill();
            roundedRect(context, 10, -38, 7, 19, 3);
            context.fill();
            context.fillStyle = 'rgba(255,255,255,.35)';
            context.fillRect(-12, -22, 24, 2);
        }

        if (clothes === 'shirt') {
            context.fillStyle = '#f4f0e9';
            context.beginPath();
            context.moveTo(-7, -39);
            context.lineTo(-2, -34);
            context.lineTo(0, -37);
            context.lineTo(2, -34);
            context.lineTo(7, -39);
            context.lineTo(4, -29);
            context.lineTo(0, -32);
            context.lineTo(-4, -29);
            context.closePath();
            context.fill();
            context.strokeStyle = 'rgba(255,255,255,.8)';
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(0, -31);
            context.lineTo(0, -19);
            context.stroke();
            context.fillStyle = '#f5ead8';
            [-28, -24].forEach(function (buttonY) {
                context.beginPath();
                context.arc(0, buttonY, .8, 0, Math.PI * 2);
                context.fill();
            });
        } else if (clothes === 'hoodie') {
            context.strokeStyle = 'rgba(255,255,255,.82)';
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(-3, -34);
            context.lineTo(-4, -27);
            context.moveTo(3, -34);
            context.lineTo(4, -27);
            context.stroke();
            context.strokeStyle = 'rgba(255,255,255,.52)';
            roundedRect(context, -7, -27, 14, 6, 2);
            context.stroke();
        } else if (clothes === 'jacket') {
            context.fillStyle = '#f4f0e9';
            roundedRect(context, -5, -36, 10, 16, 2);
            context.fill();
            context.fillStyle = shirt;
            context.beginPath();
            context.moveTo(-12, -39);
            context.lineTo(-4, -36);
            context.lineTo(-6, -19);
            context.lineTo(-12, -20);
            context.closePath();
            context.fill();
            context.beginPath();
            context.moveTo(12, -39);
            context.lineTo(4, -36);
            context.lineTo(6, -19);
            context.lineTo(12, -20);
            context.closePath();
            context.fill();
            context.strokeStyle = 'rgba(255,255,255,.85)';
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(0, -35);
            context.lineTo(0, -19);
            context.stroke();
        }

        if (style === 'hijab') {
            context.fillStyle = hair;
            context.beginPath();
            context.moveTo(-14, -57);
            context.quadraticCurveTo(-19, -74, 0, -75);
            context.quadraticCurveTo(19, -74, 14, -57);
            context.lineTo(15, -40);
            context.quadraticCurveTo(16, -36, 18, -34);
            context.quadraticCurveTo(21, -29, 19, -25);
            context.quadraticCurveTo(12, -20, 0, -18);
            context.quadraticCurveTo(-12, -20, -19, -25);
            context.quadraticCurveTo(-21, -29, -18, -34);
            context.quadraticCurveTo(-16, -36, -15, -40);
            context.closePath();
            context.fill();
        }

        context.fillStyle = skin;
        context.beginPath();
        if (style !== 'hijab') {
            context.ellipse(-12, -51, 3.5, 5, 0, 0, Math.PI * 2);
            context.ellipse(12, -51, 3.5, 5, 0, 0, Math.PI * 2);
            context.fill();
        }
        context.fillStyle = skin;
        context.beginPath();
        context.ellipse(0, -52, 12, 15, 0, 0, Math.PI * 2);
        context.fill();

        if (style === 'hijab' && facing === 'up') {
            context.fillStyle = hair;
            context.beginPath();
            context.ellipse(0, -52, 13, 16, 0, 0, Math.PI * 2);
            context.fill();
        } else if (style === 'hijab') {
            context.strokeStyle = hair;
            context.lineWidth = 1.5;
            context.lineCap = 'round';
            context.beginPath();
            context.moveTo(-12, -63);
            context.quadraticCurveTo(-15, -52, -12, -43);
            context.quadraticCurveTo(-9, -38, -5, -37);
            context.moveTo(12, -63);
            context.quadraticCurveTo(15, -52, 12, -43);
            context.quadraticCurveTo(9, -38, 5, -37);
            context.stroke();
        } else if (facing === 'up') {
            context.fillStyle = hair;
            context.beginPath();
            context.ellipse(0, -53, 13, 16, 0, 0, Math.PI * 2);
            context.fill();
        } else {
            context.fillStyle = hair;
            context.beginPath();
            if (style === 'spiky') {
                context.moveTo(-13, -52);
                context.lineTo(-12, -63);
                context.lineTo(-7, -59);
                context.lineTo(-3, -70);
                context.lineTo(1, -61);
                context.lineTo(8, -68);
                context.lineTo(8, -60);
                context.lineTo(13, -62);
                context.lineTo(13, -51);
            } else if (style === 'side') {
                context.moveTo(-13, -52);
                context.quadraticCurveTo(-16, -70, 0, -68);
                context.quadraticCurveTo(15, -67, 13, -52);
                context.lineTo(8, -55);
                context.quadraticCurveTo(3, -59, -2, -56);
                context.quadraticCurveTo(-8, -52, -13, -52);
            } else if (style === 'curly') {
                context.moveTo(-13, -52);
                context.quadraticCurveTo(-18, -70, 0, -70);
                context.quadraticCurveTo(18, -69, 13, -52);
                context.lineTo(8, -56);
                context.lineTo(3, -53);
                context.lineTo(-2, -57);
                context.lineTo(-8, -53);
            } else {
                context.moveTo(-13, -52);
                context.quadraticCurveTo(-17, -70, 0, -69);
                context.quadraticCurveTo(17, -69, 13, -52);
                context.lineTo(8, -55);
                context.lineTo(3, -53);
                context.lineTo(-2, -56);
                context.lineTo(-8, -53);
            }
            context.closePath();
            context.fill();
        }

        if (facing !== 'up' && style !== 'hijab') {
            var eyeY = -53;
            context.fillStyle = '#2c2237';
            context.beginPath();
            if (facing === 'left' || facing === 'right') {
                context.arc(facing === 'left' ? -5 : 5, eyeY, 1.5, 0, Math.PI * 2);
            } else {
                context.arc(-4, eyeY, 1.4, 0, Math.PI * 2);
                context.arc(4, eyeY, 1.4, 0, Math.PI * 2);
            }
            context.fill();
            context.strokeStyle = '#a75e62';
            context.lineWidth = 1;
            context.beginPath();
            context.arc(0, -47, 3, .15, Math.PI - .15);
            context.stroke();
        } else if (facing !== 'up') {
            context.fillStyle = '#2c2237';
            context.beginPath();
            context.arc(-4, -53, 1.4, 0, Math.PI * 2);
            context.arc(4, -53, 1.4, 0, Math.PI * 2);
            context.fill();
            context.strokeStyle = '#a75e62';
            context.lineWidth = 1;
            context.beginPath();
            context.arc(0, -47, 3, .15, Math.PI - .15);
            context.stroke();
        }

        if (accessory === 'glasses' && facing !== 'up') {
            context.strokeStyle = '#29243b';
            context.lineWidth = 1.2;
            context.fillStyle = 'rgba(226, 220, 255, .2)';
            roundedRect(context, -10, -57, 9, 7, 2);
            context.fill();
            context.stroke();
            roundedRect(context, 1, -57, 9, 7, 2);
            context.fill();
            context.stroke();
            context.beginPath();
            context.moveTo(-1, -54);
            context.lineTo(1, -54);
            context.stroke();
        } else if (accessory === 'hat') {
            context.fillStyle = '#d0c4ff';
            context.beginPath();
            context.ellipse(0, -66, 14, 5, 0, Math.PI, Math.PI * 2);
            context.fill();
            roundedRect(context, -15, -67, 30, 4, 2);
            context.fill();
        } else if (accessory === 'headphones') {
            context.strokeStyle = '#34364f';
            context.lineWidth = 3;
            context.beginPath();
            context.arc(0, -52, 15, Math.PI, Math.PI * 2);
            context.stroke();
            context.fillStyle = '#b9adff';
            roundedRect(context, -16, -55, 5, 11, 3);
            context.fill();
            roundedRect(context, 11, -55, 5, 11, 3);
            context.fill();
        } else if (accessory === 'bow') {
            context.fillStyle = '#f08caf';
            context.beginPath();
            context.moveTo(-10, -65);
            context.lineTo(-17, -70);
            context.lineTo(-17, -60);
            context.closePath();
            context.fill();
            context.beginPath();
            context.moveTo(-10, -65);
            context.lineTo(-3, -70);
            context.lineTo(-3, -60);
            context.closePath();
            context.fill();
            context.beginPath();
            context.arc(-10, -65, 2, 0, Math.PI * 2);
            context.fill();
        } else if (accessory === 'crown') {
            context.fillStyle = '#f4c95d';
            context.beginPath();
            context.moveTo(-12, -65);
            context.lineTo(-10, -75);
            context.lineTo(-4, -69);
            context.lineTo(0, -77);
            context.lineTo(5, -69);
            context.lineTo(11, -75);
            context.lineTo(12, -65);
            context.closePath();
            context.fill();
        } else if (accessory === 'bandana') {
            context.fillStyle = '#d66c91';
            context.fillRect(-12, -62, 24, 4);
            context.beginPath();
            context.moveTo(10, -61);
            context.lineTo(17, -57);
            context.lineTo(12, -52);
            context.closePath();
            context.fill();
        }

        if (label) {
            var tones = {
                self: ['rgba(16, 20, 39, .88)', '#f1ecff'],
                team: ['rgba(75, 43, 133, .94)', '#d9ffab'],
                other: ['rgba(28, 58, 89, .94)', '#e4f2ff'],
                solo: ['rgba(45, 50, 63, .94)', '#f0f1f5']
            };
            var tone = tones[presentation.tone] || tones.self;
            var subtitle = presentation.subtitle || '';
            context.font = (presentation.tone === 'self' ? '700 11px' : '600 10px') + ' system-ui, sans-serif';
            var mainWidth = Math.min(148, context.measureText(label).width + 16);
            context.font = '600 8px system-ui, sans-serif';
            var subWidth = subtitle ? context.measureText(subtitle).width + 12 : 0;
            var badgeWidth = Math.max(mainWidth, subWidth);
            var badgeHeight = subtitle ? 28 : 17;
            context.fillStyle = tone[0];
            roundedRect(context, -badgeWidth / 2, -83 - (subtitle ? 11 : 0), badgeWidth, badgeHeight, 7);
            context.fill();
            context.fillStyle = tone[1];
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.font = (presentation.tone === 'self' ? '700 11px' : '600 10px') + ' system-ui, sans-serif';
            context.fillText(label, 0, -74.5 - (subtitle ? 5 : 0), 138);
            if (subtitle) {
                context.fillStyle = '#c9d6e8';
                context.font = '600 8px system-ui, sans-serif';
                context.fillText(subtitle, 0, -67, 138);
            }
        }
        context.restore();
    };

    window.QuizAvatar.renderCanvas = function (canvas, avatar, direction) {
        var bounds = canvas.getBoundingClientRect();
        var width = Math.max(1, bounds.width || Number(canvas.getAttribute('width')) || 100);
        var height = Math.max(1, bounds.height || Number(canvas.getAttribute('height')) || 110);
        var ratio = Math.min(window.devicePixelRatio || 1, 3);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        var context = canvas.getContext('2d');
        if (!context) {
            return;
        }
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);
        var scale = Math.min(width / 34, height / 77) * .84;
        context.save();
        context.translate(width / 2, height * .88);
        context.scale(scale, scale);
        window.QuizAvatar.drawCharacter(context, 0, 0, avatar, direction || 'down', 0, '', {});
        context.restore();
    };

    function renderProfileAvatars() {
        document.querySelectorAll('canvas[data-avatar-config]').forEach(function (canvas) {
            try {
                window.QuizAvatar.renderCanvas(canvas, JSON.parse(canvas.dataset.avatarConfig), 'down');
            } catch (error) {
                console.error('Konfigurasi avatar profil tidak valid.', error);
            }
        });
    }

    renderProfileAvatars();
    window.addEventListener('resize', renderProfileAvatars);

    var dialog = document.getElementById('avatarDialog');
    var form = document.getElementById('avatarProfileForm');
    if (!dialog || !form) {
        return;
    }

    var configInput = document.getElementById('avatarConfigInput');
    var preview = dialog.querySelector('.avatar-preview-art');
    var previewName = document.getElementById('avatarPreviewName');
    var message = document.getElementById('avatarFormMessage');
    var nameInput = form.querySelector('[name="nama_panggilan"]');
    var fields = Array.prototype.slice.call(form.querySelectorAll('[data-avatar-field]'));
    var isQuizWorld = Boolean(document.getElementById('quizWorld'));
    var previewDirection = 'down';

    function currentConfig() {
        var config = {};
        fields.forEach(function (field) {
            config[field.dataset.avatarField] = field.value;
        });
        return config;
    }

    function updatePreview() {
        var config = currentConfig();
        if (window.QuizAvatar) {
            window.QuizAvatar.renderCanvas(preview, config, previewDirection);
        }
        previewName.textContent = nameInput.value.trim() || 'Pemain';
        configInput.value = JSON.stringify(config);
    }

    function closeDialog() {
        dialog.close();
    }

    document.getElementById('openAvatarModal').addEventListener('click', function () {
        message.textContent = '';
        dialog.showModal();
        updatePreview();
    });
    document.getElementById('closeAvatarModal').addEventListener('click', closeDialog);
    document.getElementById('cancelAvatarModal').addEventListener('click', closeDialog);
    fields.forEach(function (field) { field.addEventListener('change', updatePreview); });
    nameInput.addEventListener('input', updatePreview);
    dialog.querySelectorAll('[data-avatar-facing]').forEach(function (button) {
        button.addEventListener('click', function () {
            previewDirection = button.dataset.avatarFacing;
            dialog.querySelectorAll('[data-avatar-facing]').forEach(function (otherButton) {
                var selected = otherButton === button;
                otherButton.classList.toggle('is-selected', selected);
                otherButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            updatePreview();
        });
    });
    dialog.querySelector('[data-avatar-randomize]').addEventListener('click', function () {
        fields.forEach(function (field) {
            field.selectedIndex = Math.floor(Math.random() * field.options.length);
        });
        updatePreview();
    });
    window.addEventListener('resize', function () {
        if (dialog.open) {
            updatePreview();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        updatePreview();
        message.textContent = 'Menyimpan profil...';
        var submitButton = form.querySelector('[type="submit"]');
        submitButton.disabled = true;
        var formData = new FormData(form);
        if (isQuizWorld) {
            formData.append('ajax', '1');
        }
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Server tidak mengirim respons profil yang valid.');
            }).then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.error || 'Profil belum dapat disimpan.');
                }
                return payload;
            });
        }).then(function (payload) {
            if (isQuizWorld) {
                window.dispatchEvent(new CustomEvent('quiz-world:avatar-updated', {
                    detail: payload
                }));
                message.textContent = 'Profil tersimpan.';
                dialog.close();
                return;
            }

            message.textContent = 'Profil tersimpan. Memuat ulang halaman...';
            window.location.reload();
        }).catch(function (error) {
            message.textContent = error.message;
        }).finally(function () {
            submitButton.disabled = false;
        });
    });

    updatePreview();
}());
