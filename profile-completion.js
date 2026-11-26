(function () {
    var form = document.querySelector('#googleProfileForm');
    if (!form) {
        return;
    }

    var country = document.querySelector('#googleProfileCountry');
    var regionMessage = document.querySelector('#googleProfileRegionMessage');
    var formMessage = document.querySelector('#googleProfileMessage');
    var manualButton = document.querySelector('#googleProfileManualButton');
    var wilayah = [
        { id: 'googleProfileProvince', manualId: 'googleProfileProvinceManual', name: 'provinsi', type: 'provinces' },
        { id: 'googleProfileCity', manualId: 'googleProfileCityManual', name: 'kabupaten_kota', type: 'regencies' },
        { id: 'googleProfileDistrict', manualId: 'googleProfileDistrictManual', name: 'kecamatan', type: 'districts' },
        { id: 'googleProfileVillage', manualId: 'googleProfileVillageManual', name: 'kelurahan_desa', type: 'villages' }
    ];
    var gunakanApi = true;
    var tokenPermintaanWilayah = [0, 0, 0, 0];

    document.body.classList.add('modal-open');

    function pilihan(id) {
        return document.getElementById(id);
    }

    function aturPesan(element, text) {
        element.textContent = text;
        element.hidden = text === '';
    }

    function resetPilihan(index, text) {
        for (var i = index; i < wilayah.length; i += 1) {
            var select = pilihan(wilayah[i].id);
            select.textContent = '';
            var option = document.createElement('option');
            option.value = '';
            option.textContent = i === index ? text : 'Pilih wilayah sebelumnya terlebih dahulu';
            select.appendChild(option);
            select.disabled = true;
        }
    }

    function setPilihan(select, rows, text) {
        select.textContent = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = text;
        select.appendChild(placeholder);

        rows.forEach(function (row) {
            var option = document.createElement('option');
            option.value = row.name;
            option.textContent = row.name;
            option.dataset.code = row.code;
            select.appendChild(option);
        });
        select.disabled = rows.length === 0;
    }

    function setModeApi(enabled) {
        gunakanApi = enabled;
        if (!enabled) {
            tokenPermintaanWilayah = tokenPermintaanWilayah.map(function (token) {
                return token + 1;
            });
        }
        wilayah.forEach(function (item) {
            var select = pilihan(item.id);
            var manual = pilihan(item.manualId);
            if (!enabled && select.value) {
                manual.value = select.value;
            }
            select.hidden = !enabled;
            select.disabled = !enabled || select.options.length < 2;
            select.required = enabled;
            select.name = enabled ? item.name : '';
            manual.hidden = enabled;
            manual.disabled = enabled;
            manual.required = !enabled;
            manual.name = enabled ? '' : item.name;
            manual.placeholder = 'Masukkan ' + item.name.replace('_', ' / ');
        });
        manualButton.hidden = !enabled;
    }

    function ambilWilayah(type, code) {
        var query = new URLSearchParams({ type: type });
        if (code) {
            query.set('code', code);
        }
        return fetch('../Koneksi/wilayah-api.php?' + query.toString(), {
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !Array.isArray(result.data)) {
                    throw new Error(result.message || 'Data wilayah belum tersedia.');
                }
                return result.data;
            });
        });
    }

    function pakaiAlamatManual(message) {
        wilayah.forEach(function (item) {
            var select = pilihan(item.id);
            if (select.value) {
                pilihan(item.manualId).value = select.value;
            }
        });
        setModeApi(false);
        resetPilihan(0, 'Isi wilayah secara manual');
        aturPesan(regionMessage, message);
    }

    function muatProvinsi() {
        var token = tokenPermintaanWilayah[0] + 1;
        tokenPermintaanWilayah[0] = token;
        tokenPermintaanWilayah[1] += 1;
        tokenPermintaanWilayah[2] += 1;
        tokenPermintaanWilayah[3] += 1;
        aturPesan(regionMessage, '');
        setModeApi(true);
        resetPilihan(0, 'Memuat provinsi...');
        ambilWilayah('provinces', '').then(function (rows) {
            if (token !== tokenPermintaanWilayah[0] || country.value !== 'Indonesia' || !gunakanApi) {
                return;
            }
            setPilihan(pilihan(wilayah[0].id), rows, 'Pilih provinsi');
            resetPilihan(1, 'Pilih kabupaten / kota terlebih dahulu');
        }).catch(function () {
            if (token === tokenPermintaanWilayah[0] && country.value === 'Indonesia' && gunakanApi) {
                pakaiAlamatManual('Data wilayah sedang tidak tersedia. Silakan isi alamat secara manual.');
            }
        });
    }

    function muatTurunan(index, code, message) {
        var token = tokenPermintaanWilayah[index] + 1;
        tokenPermintaanWilayah[index] = token;
        for (var child = index + 1; child < wilayah.length; child += 1) {
            tokenPermintaanWilayah[child] += 1;
        }
        resetPilihan(index, message);
        if (!code) {
            return;
        }
        ambilWilayah(wilayah[index].type, code).then(function (rows) {
            var parent = pilihan(wilayah[index - 1].id);
            var selected = parent.selectedOptions[0];
            if (
                token !== tokenPermintaanWilayah[index] ||
                !gunakanApi ||
                !selected ||
                selected.dataset.code !== code
            ) {
                return;
            }
            setPilihan(pilihan(wilayah[index].id), rows, message.replace('Memuat ', 'Pilih '));
            if (index + 1 < wilayah.length) {
                resetPilihan(index + 1, 'Pilih wilayah sebelumnya terlebih dahulu');
            }
        }).catch(function () {
            if (token === tokenPermintaanWilayah[index] && gunakanApi) {
                pakaiAlamatManual('Data wilayah sedang tidak tersedia. Silakan isi alamat secara manual.');
            }
        });
    }

    pilihan(wilayah[0].id).addEventListener('change', function (event) {
        var selected = event.target.selectedOptions[0];
        muatTurunan(1, selected ? selected.dataset.code : '', 'Memuat kabupaten / kota...');
    });
    pilihan(wilayah[1].id).addEventListener('change', function (event) {
        var selected = event.target.selectedOptions[0];
        muatTurunan(2, selected ? selected.dataset.code : '', 'Memuat kecamatan...');
    });
    pilihan(wilayah[2].id).addEventListener('change', function (event) {
        var selected = event.target.selectedOptions[0];
        muatTurunan(3, selected ? selected.dataset.code : '', 'Memuat kelurahan / desa...');
    });
    country.addEventListener('change', function () {
        if (country.value === 'Indonesia') {
            muatProvinsi();
        } else {
            wilayah.forEach(function (item) {
                pilihan(item.id).value = '';
                pilihan(item.manualId).value = '';
            });
            pakaiAlamatManual('Untuk negara selain Indonesia, silakan isi alamat wilayah secara manual.');
        }
    });

    manualButton.addEventListener('click', function () {
        pakaiAlamatManual('Silakan isi Provinsi, Kabupaten / Kota, Kecamatan, dan Kelurahan / Desa.');
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        aturPesan(formMessage, '');
        if (gunakanApi && wilayah.some(function (item) { return pilihan(item.id).disabled; })) {
            aturPesan(formMessage, 'Tunggu data wilayah selesai dimuat dan pilih alamat sampai Kelurahan / Desa.');
            return;
        }

        var payload = {};
        new FormData(form).forEach(function (value, key) {
            payload[key] = value;
        });
        fetch('../Koneksi/account-api.php?action=lengkapi-profil-google', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok) {
                    throw new Error(result.message || 'Profil belum dapat disimpan.');
                }
                return result;
            });
        }).then(function (result) {
            window.location.href = result.redirect || 'Beranda.php';
        }).catch(function (error) {
            aturPesan(formMessage, error.message || 'Tidak dapat terhubung ke server. Coba lagi.');
        });
    });

    muatProvinsi();
})();
