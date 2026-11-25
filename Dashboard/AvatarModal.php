<dialog class="avatar-dialog" id="avatarDialog" aria-labelledby="avatarDialogTitle">
    <div class="avatar-dialog-card">
        <button class="avatar-dialog-close" type="button" id="closeAvatarModal" aria-label="Tutup">×</button>
        <div class="avatar-dialog-heading">
            <p class="player-eyebrow">Identitas pemain</p>
            <h2 id="avatarDialogTitle">Edit Profil &amp; Sesuaikan Avatar</h2>
            <p>Atur nama panggilan dan tampilan karakter 2D-mu.</p>
        </div>
        <form id="avatarProfileForm" action="../Koneksi/avatar.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escapeKuis($csrf) ?>">
            <input type="hidden" name="avatar_config" id="avatarConfigInput">
            <div class="avatar-editor-layout">
                <section class="avatar-preview-panel" aria-label="Pratinjau avatar">
                    <canvas class="avatar-preview-art" width="200" height="220" role="img" aria-label="Pratinjau karakter"></canvas>
                    <strong id="avatarPreviewName"><?= escapeKuis($namaPanggilan) ?></strong>
                    <span>PRATINJAU KARAKTER</span>
                    <div class="avatar-preview-controls" role="group" aria-label="Arah pratinjau avatar">
                        <button type="button" class="is-selected" data-avatar-facing="down" aria-pressed="true">Depan</button>
                        <button type="button" data-avatar-facing="right" aria-pressed="false">Samping</button>
                        <button type="button" data-avatar-facing="up" aria-pressed="false">Belakang</button>
                    </div>
                    <button class="avatar-randomize-button" type="button" data-avatar-randomize>Acak Avatar</button>
                </section>
                <div class="avatar-fields">
                    <label class="avatar-field">
                        <span>Nama Panggilan</span>
                        <input type="text" name="nama_panggilan" maxlength="30" value="<?= escapeKuis($namaPanggilan) ?>" required>
                    </label>
                    <label class="avatar-field">
                        <span>Warna Kulit</span>
                        <select data-avatar-field="skin">
                            <option value="#f2c6a0" <?= $avatarConfig['skin'] === '#f2c6a0' ? 'selected' : '' ?>>Sawo matang terang</option>
                            <option value="#e6ae82" <?= $avatarConfig['skin'] === '#e6ae82' ? 'selected' : '' ?>>Hangat</option>
                            <option value="#bd805e" <?= $avatarConfig['skin'] === '#bd805e' ? 'selected' : '' ?>>Cokelat</option>
                            <option value="#85543f" <?= $avatarConfig['skin'] === '#85543f' ? 'selected' : '' ?>>Gelap</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Gaya Rambut</span>
                        <select data-avatar-field="hair_style">
                            <option value="short" <?= $avatarConfig['hair_style'] === 'short' ? 'selected' : '' ?>>Pendek</option>
                            <option value="side" <?= $avatarConfig['hair_style'] === 'side' ? 'selected' : '' ?>>Belah Samping</option>
                            <option value="spiky" <?= $avatarConfig['hair_style'] === 'spiky' ? 'selected' : '' ?>>Spiky</option>
                            <option value="bob" <?= $avatarConfig['hair_style'] === 'bob' ? 'selected' : '' ?>>Bob</option>
                            <option value="long" <?= $avatarConfig['hair_style'] === 'long' ? 'selected' : '' ?>>Panjang</option>
                            <option value="pony" <?= $avatarConfig['hair_style'] === 'pony' ? 'selected' : '' ?>>Kuncir / Ponytail</option>
                            <option value="curly" <?= $avatarConfig['hair_style'] === 'curly' ? 'selected' : '' ?>>Keriting</option>
                            <option value="hijab" <?= $avatarConfig['hair_style'] === 'hijab' ? 'selected' : '' ?>>Hijab</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Warna Rambut / Hijab</span>
                        <select data-avatar-field="hair_color">
                            <option value="#38234f" <?= $avatarConfig['hair_color'] === '#38234f' ? 'selected' : '' ?>>Ungu gelap</option>
                            <option value="#171a2f" <?= $avatarConfig['hair_color'] === '#171a2f' ? 'selected' : '' ?>>Hitam</option>
                            <option value="#9c4f45" <?= $avatarConfig['hair_color'] === '#9c4f45' ? 'selected' : '' ?>>Merah kecokelatan</option>
                            <option value="#d6a64f" <?= $avatarConfig['hair_color'] === '#d6a64f' ? 'selected' : '' ?>>Keemasan</option>
                            <option value="#5b3b2e" <?= $avatarConfig['hair_color'] === '#5b3b2e' ? 'selected' : '' ?>>Cokelat</option>
                            <option value="#e7d4a3" <?= $avatarConfig['hair_color'] === '#e7d4a3' ? 'selected' : '' ?>>Pirang</option>
                            <option value="#d66c91" <?= $avatarConfig['hair_color'] === '#d66c91' ? 'selected' : '' ?>>Merah muda</option>
                            <option value="#536b9c" <?= $avatarConfig['hair_color'] === '#536b9c' ? 'selected' : '' ?>>Biru</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Model Baju</span>
                        <select data-avatar-field="shirt_style">
                            <option value="tshirt" <?= $avatarConfig['shirt_style'] === 'tshirt' ? 'selected' : '' ?>>Kaos</option>
                            <option value="shirt" <?= $avatarConfig['shirt_style'] === 'shirt' ? 'selected' : '' ?>>Kemeja</option>
                            <option value="hoodie" <?= $avatarConfig['shirt_style'] === 'hoodie' ? 'selected' : '' ?>>Hoodie</option>
                            <option value="jacket" <?= $avatarConfig['shirt_style'] === 'jacket' ? 'selected' : '' ?>>Jaket</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Warna Baju</span>
                        <select data-avatar-field="shirt_color">
                            <option value="#a78bfa" <?= $avatarConfig['shirt_color'] === '#a78bfa' ? 'selected' : '' ?>>Ungu pastel</option>
                            <option value="#f08caf" <?= $avatarConfig['shirt_color'] === '#f08caf' ? 'selected' : '' ?>>Merah muda</option>
                            <option value="#70c8d6" <?= $avatarConfig['shirt_color'] === '#70c8d6' ? 'selected' : '' ?>>Biru muda</option>
                            <option value="#f0b45d" <?= $avatarConfig['shirt_color'] === '#f0b45d' ? 'selected' : '' ?>>Jingga</option>
                            <option value="#72bd91" <?= $avatarConfig['shirt_color'] === '#72bd91' ? 'selected' : '' ?>>Hijau</option>
                            <option value="#e76f51" <?= $avatarConfig['shirt_color'] === '#e76f51' ? 'selected' : '' ?>>Koral</option>
                            <option value="#5266b8" <?= $avatarConfig['shirt_color'] === '#5266b8' ? 'selected' : '' ?>>Biru</option>
                            <option value="#e6e8f0" <?= $avatarConfig['shirt_color'] === '#e6e8f0' ? 'selected' : '' ?>>Putih</option>
                            <option value="#34364f" <?= $avatarConfig['shirt_color'] === '#34364f' ? 'selected' : '' ?>>Navy</option>
                            <option value="#b34f68" <?= $avatarConfig['shirt_color'] === '#b34f68' ? 'selected' : '' ?>>Merah marun</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Warna Celana</span>
                        <select data-avatar-field="pants_color">
                            <option value="#5266b8" <?= $avatarConfig['pants_color'] === '#5266b8' ? 'selected' : '' ?>>Biru</option>
                            <option value="#536276" <?= $avatarConfig['pants_color'] === '#536276' ? 'selected' : '' ?>>Abu-abu</option>
                            <option value="#684d87" <?= $avatarConfig['pants_color'] === '#684d87' ? 'selected' : '' ?>>Ungu</option>
                            <option value="#387f78" <?= $avatarConfig['pants_color'] === '#387f78' ? 'selected' : '' ?>>Hijau tua</option>
                            <option value="#222b3c" <?= $avatarConfig['pants_color'] === '#222b3c' ? 'selected' : '' ?>>Navy gelap</option>
                            <option value="#9b6b46" <?= $avatarConfig['pants_color'] === '#9b6b46' ? 'selected' : '' ?>>Cokelat</option>
                        </select>
                    </label>
                    <label class="avatar-field">
                        <span>Aksesori</span>
                        <select data-avatar-field="accessory">
                            <option value="none" <?= $avatarConfig['accessory'] === 'none' ? 'selected' : '' ?>>Tanpa aksesori</option>
                            <option value="glasses" <?= $avatarConfig['accessory'] === 'glasses' ? 'selected' : '' ?>>Kacamata</option>
                            <option value="hat" <?= $avatarConfig['accessory'] === 'hat' ? 'selected' : '' ?>>Topi</option>
                            <option value="headphones" <?= $avatarConfig['accessory'] === 'headphones' ? 'selected' : '' ?>>Headphone</option>
                            <option value="bow" <?= $avatarConfig['accessory'] === 'bow' ? 'selected' : '' ?>>Pita</option>
                            <option value="crown" <?= $avatarConfig['accessory'] === 'crown' ? 'selected' : '' ?>>Mahkota</option>
                            <option value="bandana" <?= $avatarConfig['accessory'] === 'bandana' ? 'selected' : '' ?>>Bandana</option>
                        </select>
                    </label>
                </div>
            </div>
            <p class="avatar-form-message" id="avatarFormMessage" role="status" aria-live="polite"></p>
            <div class="avatar-dialog-actions">
                <button class="avatar-save-button" type="submit">Simpan Profil</button>
                <button class="avatar-cancel-button" type="button" id="cancelAvatarModal">Batal</button>
            </div>
        </form>
    </div>
</dialog>
