var tombolLogin = document.querySelectorAll('#loginButton, #heroLoginButton, #groupLoginButton');
var tombolDaftar = document.querySelectorAll('#registerButton, #heroRegisterButton, #groupRegisterButton');
var modalLogin = document.querySelector('#loginModal');
var modalDaftar = document.querySelector('#registerModal');
var tombolTutup = document.querySelectorAll('.close-button');
var inputPassword = document.querySelector('#password');
var tombolLihatPassword = document.querySelector('.toggle-password');
var pesanLogin = document.querySelector('#loginMessage');
var formDaftar = document.querySelector('#registerForm');
var pesanDaftar = document.querySelector('#registerMessage');
var negaraInput = document.querySelector('#negara');
var pesanApiNegara = document.querySelector('#countryApiMessage');
var pesanApiWilayah = document.querySelector('#regionApiMessage');
var tombolAlamatManual = document.querySelector('#useManualAddressButton');
var tokenPermintaanWilayah = [0, 0, 0, 0];
var alamatWilayah = [
    { id: 'provinsi', manualId: 'provinsiManual', name: 'provinsi' },
    { id: 'kabupatenKota', manualId: 'kabupatenKotaManual', name: 'kabupaten_kota' },
    { id: 'kecamatan', manualId: 'kecamatanManual', name: 'kecamatan' },
    { id: 'kelurahanDesa', manualId: 'kelurahanDesaManual', name: 'kelurahan_desa' }
];

function perbaruiStatusScrollModal() {
    document.body.classList.toggle(
        'modal-open',
        (modalLogin && !modalLogin.hidden) || (modalDaftar && !modalDaftar.hidden)
    );
}

function bukaModalLogin() {
    modalLogin.hidden = false;
    perbaruiStatusScrollModal();
}

function bukaModalDaftar() {
    modalDaftar.hidden = false;
    perbaruiStatusScrollModal();
}

function tutupModalLogin() {
    modalLogin.hidden = true;
    perbaruiStatusScrollModal();
}

function tutupModalDaftar() {
    modalDaftar.hidden = true;
    perbaruiStatusScrollModal();
}

tombolLogin.forEach(function (tombol) {
    tombol.addEventListener('click', function (event) {
        event.preventDefault();
        bukaModalLogin();
    });
});

tombolDaftar.forEach(function (tombol) {
    tombol.addEventListener('click', function (event) {
        event.preventDefault();
        bukaModalDaftar();
    });
});

document.querySelector('#openRegisterFromLogin').addEventListener('click', function () {
    tutupModalLogin();
    bukaModalDaftar();
});

document.querySelector('#openLoginFromRegister').addEventListener('click', function () {
    tutupModalDaftar();
    bukaModalLogin();
});

tombolTutup.forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        tutupModalLogin();
        tutupModalDaftar();
    });
});

modalLogin.addEventListener('click', function (event) {
    if (event.target === modalLogin) {
        tutupModalLogin();
    }
});

modalDaftar.addEventListener('click', function (event) {
    if (event.target === modalDaftar) {
        tutupModalDaftar();
    }
});

tombolLihatPassword.addEventListener('click', function () {
    if (inputPassword.type === 'password') {
        inputPassword.type = 'text';
    } else {
        inputPassword.type = 'password';
    }
});

function isiPilihanWilayah(pilihan, data, teksAwal) {
    pilihan.textContent = '';

    var opsiAwal = document.createElement('option');
    opsiAwal.value = '';
    opsiAwal.textContent = teksAwal;
    opsiAwal.selected = true;
    pilihan.appendChild(opsiAwal);

    data.forEach(function (wilayah) {
        var opsi = document.createElement('option');
        opsi.value = wilayah.name;
        opsi.textContent = wilayah.name;
        opsi.dataset.code = wilayah.code;
        pilihan.appendChild(opsi);
    });

    pilihan.disabled = data.length === 0;
}

function resetPilihanWilayah(index, teksAwal) {
    for (var i = index; i < alamatWilayah.length; i += 1) {
        var pilihan = document.querySelector('#' + alamatWilayah[i].id);
        pilihan.textContent = '';

        var opsi = document.createElement('option');
        opsi.value = '';
        opsi.textContent = i === index ? teksAwal : 'Pilih wilayah sebelumnya terlebih dahulu';
        opsi.selected = true;
        pilihan.appendChild(opsi);
        pilihan.disabled = true;
    }
}

function ubahModeAlamat(pakaiApi) {
    alamatWilayah.forEach(function (item) {
        var pilihan = document.querySelector('#' + item.id);
        var inputManual = document.querySelector('#' + item.manualId);

        pilihan.hidden = !pakaiApi;
        pilihan.disabled = !pakaiApi || pilihan.options.length < 2;
        pilihan.required = pakaiApi;
        inputManual.hidden = pakaiApi;
        inputManual.disabled = pakaiApi;
        inputManual.required = !pakaiApi;
        pilihan.name = pakaiApi ? item.name : '';
        inputManual.name = pakaiApi ? '' : item.name;
        inputManual.placeholder = item.name === 'kelurahan_desa'
            ? 'Masukkan kelurahan / desa'
            : 'Masukkan ' + item.name.replace('_', ' / ');
    });
    tombolAlamatManual.hidden = !pakaiApi;
}

function tampilkanAlamatManual(pesan) {
    alamatWilayah.forEach(function (item) {
        var pilihan = document.querySelector('#' + item.id);
        var inputManual = document.querySelector('#' + item.manualId);
        if (pilihan.value) {
            inputManual.value = pilihan.value;
        }
    });
    ubahModeAlamat(false);
    resetPilihanWilayah(0, 'Pilih provinsi terlebih dahulu');
    pesanApiWilayah.textContent = pesan;
    pesanApiWilayah.hidden = false;
}

function tampilkanErrorApiWilayah() {
    tampilkanAlamatManual(
        'Data wilayah sedang tidak tersedia. Alamat manual sudah diaktifkan; Anda tetap dapat menyelesaikan pendaftaran.'
    );
}

function ambilDariApiLokal(tipe, kode) {
    var parameter = new URLSearchParams({ type: tipe });
    if (kode) {
        parameter.set('code', kode);
    }

    return fetch('Koneksi/wilayah-api.php?' + parameter.toString(), {
        headers: { 'Accept': 'application/json' }
    }).then(function (response) {
        return response.json().then(function (hasil) {
            if (!response.ok) {
                throw new Error(hasil.message || 'Endpoint wilayah merespons ' + response.status + '.');
            }

            if (!Array.isArray(hasil.data)) {
                throw new Error('Format data wilayah tidak sesuai.');
            }

            return hasil;
        });
    });
}

function muatProvinsi() {
    var token = tokenPermintaanWilayah[0] + 1;
    tokenPermintaanWilayah[0] = token;
    tokenPermintaanWilayah[1] += 1;
    tokenPermintaanWilayah[2] += 1;
    tokenPermintaanWilayah[3] += 1;
    pesanApiWilayah.hidden = true;
    ubahModeAlamat(true);
    resetPilihanWilayah(0, 'Memuat provinsi...');

    return ambilDariApiLokal('provinces')
        .then(function (hasil) {
            if (token !== tokenPermintaanWilayah[0] || negaraInput.value !== 'Indonesia') {
                return;
            }

            isiPilihanWilayah(document.querySelector('#provinsi'), hasil.data, 'Pilih provinsi');
            resetPilihanWilayah(1, 'Pilih kabupaten / kota terlebih dahulu');
        })
        .catch(function () {
            if (token === tokenPermintaanWilayah[0] && negaraInput.value === 'Indonesia') {
                tampilkanErrorApiWilayah();
            }
        });
}

negaraInput.addEventListener('change', function () {
    tokenPermintaanWilayah.forEach(function (token, index) {
        tokenPermintaanWilayah[index] += 1;
    });
    alamatWilayah.forEach(function (item) {
        document.querySelector('#' + item.manualId).value = '';
    });

    if (negaraInput.value === 'Indonesia') {
        muatProvinsi();
    } else {
        resetPilihanWilayah(0, 'Isi wilayah secara manual');
        tampilkanAlamatManual('Untuk negara selain Indonesia, silakan isi alamat wilayah secara manual.');
    }
});

tombolAlamatManual.addEventListener('click', function () {
    tokenPermintaanWilayah.forEach(function (token, index) {
        tokenPermintaanWilayah[index] += 1;
    });
    tampilkanAlamatManual('Silakan isi Provinsi, Kabupaten / Kota, Kecamatan, dan Kelurahan / Desa secara manual.');
});

document.querySelector('#provinsi').addEventListener('change', function (event) {
    var opsiTerpilih = event.target.selectedOptions[0];
    var kodeProvinsi = opsiTerpilih ? opsiTerpilih.dataset.code : '';
    var token = tokenPermintaanWilayah[1] + 1;
    tokenPermintaanWilayah[1] = token;
    tokenPermintaanWilayah[2] += 1;
    tokenPermintaanWilayah[3] += 1;
    resetPilihanWilayah(1, 'Memuat kabupaten / kota...');

    if (!kodeProvinsi) {
        return;
    }

    ambilDariApiLokal('regencies', kodeProvinsi)
        .then(function (hasil) {
            if (token !== tokenPermintaanWilayah[1] || negaraInput.value !== 'Indonesia') {
                return;
            }

            isiPilihanWilayah(document.querySelector('#kabupatenKota'), hasil.data, 'Pilih kabupaten / kota');
            resetPilihanWilayah(2, 'Pilih kecamatan terlebih dahulu');
        })
        .catch(function () {
            if (token === tokenPermintaanWilayah[1] && negaraInput.value === 'Indonesia') {
                tampilkanErrorApiWilayah();
            }
        });
});

document.querySelector('#kabupatenKota').addEventListener('change', function (event) {
    var opsiTerpilih = event.target.selectedOptions[0];
    var kodeKabupaten = opsiTerpilih ? opsiTerpilih.dataset.code : '';
    var token = tokenPermintaanWilayah[2] + 1;
    tokenPermintaanWilayah[2] = token;
    tokenPermintaanWilayah[3] += 1;
    resetPilihanWilayah(2, 'Memuat kecamatan...');

    if (!kodeKabupaten) {
        return;
    }

    ambilDariApiLokal('districts', kodeKabupaten)
        .then(function (hasil) {
            if (token !== tokenPermintaanWilayah[2] || negaraInput.value !== 'Indonesia') {
                return;
            }

            isiPilihanWilayah(document.querySelector('#kecamatan'), hasil.data, 'Pilih kecamatan');
            resetPilihanWilayah(3, 'Pilih kelurahan / desa terlebih dahulu');
        })
        .catch(function () {
            if (token === tokenPermintaanWilayah[2] && negaraInput.value === 'Indonesia') {
                tampilkanErrorApiWilayah();
            }
        });
});

document.querySelector('#kecamatan').addEventListener('change', function (event) {
    var opsiTerpilih = event.target.selectedOptions[0];
    var kodeKecamatan = opsiTerpilih ? opsiTerpilih.dataset.code : '';
    var token = tokenPermintaanWilayah[3] + 1;
    tokenPermintaanWilayah[3] = token;
    resetPilihanWilayah(3, 'Memuat kelurahan / desa...');

    if (!kodeKecamatan) {
        return;
    }

    ambilDariApiLokal('villages', kodeKecamatan)
        .then(function (hasil) {
            if (token !== tokenPermintaanWilayah[3] || negaraInput.value !== 'Indonesia') {
                return;
            }

            isiPilihanWilayah(document.querySelector('#kelurahanDesa'), hasil.data, 'Pilih kelurahan / desa');
        })
        .catch(function () {
            if (token === tokenPermintaanWilayah[3] && negaraInput.value === 'Indonesia') {
                tampilkanErrorApiWilayah();
            }
        });
});

function muatDaftarNegara() {
    var daftarNegara = ['Indonesia', 'Malaysia', 'Singapura', 'Brunei Darussalam', 'Timor-Leste', 'Lainnya'];
    negaraInput.textContent = '';
    daftarNegara.forEach(function (nama) {
        var opsi = document.createElement('option');
        opsi.value = nama;
        opsi.textContent = nama;
        negaraInput.appendChild(opsi);
    });
    negaraInput.value = 'Indonesia';
    pesanApiNegara.hidden = true;
    return Promise.resolve();
}

muatDaftarNegara();
muatProvinsi();

var sectionLinks = Array.prototype.slice.call(document.querySelectorAll('.menu a[href^="#"]'))
    .filter(function (link) {
        return ['#beranda', '#cara-main', '#kelompok'].indexOf(link.getAttribute('href')) !== -1;
    });
var landingSections = sectionLinks.map(function (link) {
    return document.querySelector(link.getAttribute('href'));
}).filter(Boolean);

function setActiveSection(sectionId) {
    sectionLinks.forEach(function (link) {
        var active = link.getAttribute('href') === '#' + sectionId;
        link.classList.toggle('active', active);
        if (active) {
            link.setAttribute('aria-current', 'location');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

sectionLinks.forEach(function (link) {
    link.addEventListener('click', function (event) {
        var sectionId = link.getAttribute('href').slice(1);
        var section = document.getElementById(sectionId);
        if (!section) {
            return;
        }
        event.preventDefault();
        if (sectionId === 'beranda') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        setActiveSection(sectionId);
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + sectionId);
        }
    });
});

var scrollSpyFrame = 0;
window.addEventListener('scroll', function () {
    if (scrollSpyFrame) {
        return;
    }
    scrollSpyFrame = window.requestAnimationFrame(function () {
        scrollSpyFrame = 0;
        var selected = 'beranda';
        var marker = window.scrollY + document.querySelector('.site-header').offsetHeight + 40;
        landingSections.forEach(function (section) {
            if (section.offsetTop <= marker) {
                selected = section.id;
            }
        });
        setActiveSection(selected);
    });
}, { passive: true });

if (window.location.hash && document.getElementById(window.location.hash.slice(1))) {
    setActiveSection(window.location.hash.slice(1));
}

formDaftar.addEventListener('submit', function (event) {
    event.preventDefault();
    pesanDaftar.hidden = true;

    if (
        negaraInput.value === 'Indonesia' &&
        !document.querySelector('#provinsi').hidden &&
        alamatWilayah.some(function (item) {
            return document.querySelector('#' + item.id).disabled;
        })
    ) {
        pesanDaftar.textContent = 'Tunggu daftar wilayah selesai dimuat dan pilih alamat sampai Kelurahan / Desa.';
        pesanDaftar.hidden = false;
        return;
    }

    var dataDaftar = {};
    new FormData(formDaftar).forEach(function (nilai, nama) {
        dataDaftar[nama] = nilai;
    });

    fetch('Koneksi/account-api.php?action=daftar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(dataDaftar)
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (hasil) {
            if (!hasil.ok) {
                throw new Error(hasil.data.message || 'Pendaftaran gagal. Periksa kembali data Anda.');
            }

            window.location.href = hasil.data.redirect;
        })
        .catch(function (error) {
            pesanDaftar.textContent = error.message || 'Tidak dapat terhubung ke server. Coba lagi.';
            pesanDaftar.hidden = false;
        });
});

var parameterHalaman = new URLSearchParams(window.location.search);
var adaPesanError = parameterHalaman.has('error');

if (adaPesanError) {
    bukaModalLogin();
    pesanLogin.hidden = false;
    pesanLogin.style.display = 'block';

    if (parameterHalaman.get('error') === 'setup') {
        pesanLogin.textContent = 'Database Quiz Game belum siap. Import database/quiz_game.sql melalui phpMyAdmin.';
    } else if (parameterHalaman.get('error') === 'auth') {
        pesanLogin.textContent = 'Silakan masuk terlebih dahulu.';
    } else {
        pesanLogin.textContent = 'Email atau password salah!';
        window.setTimeout(function () {
            pesanLogin.hidden = true;
            pesanLogin.style.display = 'none';
        }, 2000);
    }

    window.history.replaceState({}, document.title, window.location.pathname);
}

if (parameterHalaman.has('register')) {
    bukaModalDaftar();
    pesanDaftar.textContent = parameterHalaman.get('register') === 'setup'
        ? 'Database Quiz Game belum siap. Import database/quiz_game.sql melalui phpMyAdmin.'
        : 'Pendaftaran gagal. Silakan coba lagi.';
    pesanDaftar.hidden = false;
    window.history.replaceState({}, document.title, window.location.pathname);
}

if (parameterHalaman.has('google_error')) {
    var modeGoogle = parameterHalaman.get('mode') === 'register' ? 'register' : 'login';
    var pesanGoogle = {
        config: 'Login Google belum dikonfigurasi di server. Silakan gunakan email dan password atau hubungi pengelola.',
        no_account: 'Akun Google ini belum terdaftar. Pilih Daftar dengan Google untuk membuat akun baru.',
        email_conflict: 'Email ini sudah terhubung ke akun Google lain. Silakan gunakan email Google yang sesuai.',
        state: 'Sesi Google kedaluwarsa atau tidak valid. Silakan coba lagi.',
        cancelled: 'Proses autentikasi Google dibatalkan.',
        failed: 'Autentikasi Google belum berhasil. Silakan coba lagi.'
    };
    var elemenPesanGoogle = document.querySelector(
        modeGoogle === 'register' ? '#googleRegisterMessage' : '#googleLoginMessage'
    );
    if (modeGoogle === 'register') {
        bukaModalDaftar();
    } else {
        bukaModalLogin();
    }
    elemenPesanGoogle.textContent = pesanGoogle[parameterHalaman.get('google_error')] || pesanGoogle.failed;
    elemenPesanGoogle.hidden = false;
    window.history.replaceState({}, document.title, window.location.pathname);
}