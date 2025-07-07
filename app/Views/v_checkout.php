<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-6">
        <!-- Vertical Form -->
        <?= form_open('buy', 'class="row g-3"') ?>
        <?= form_hidden('username', session()->get('username')) ?>
        <?= form_input(['type' => 'hidden', 'name' => 'total_harga', 'id' => 'total_harga', 'value' => '']) ?>
        <div class="col-12">
            <label for="nama" class="form-label">Nama</label>
            <input type="text" class="form-control" id="nama" value="<?php echo session()->get('username'); ?>" required>
        </div>
        <div class="col-12">
            <label for="alamat" class="form-label">Alamat</label>
            <input type="text" class="form-control" id="alamat" name="alamat" required>
        </div>
        <div class="col-12">
            <label for="kelurahan" class="form-label">Kelurahan</label>
            <select class="form-control" id="kelurahan" name="kelurahan" required></select>
        </div>
        <div class="col-12">
            <label for="courier" class="form-label">Kurir</label>
            <select class="form-control" id="courier" name="courier" required>
                <option value="">Pilih Kurir</option>
                <option value="jne">JNE</option>
                <option value="jnt">J&T Express</option>
                <option value="sicepat">SiCepat</option>
                <option value="tiki">TIKI</option>
                <option value="pos">POS Indonesia</option>
                <option value="anteraja">AnterAja</option>
                <option value="lion">Lion Parcel</option>
                <option value="ninja">Ninja Express</option>
            </select>
        </div>
        <div class="col-12">
            <label for="layanan" class="form-label">Layanan</label>
            <select class="form-control" id="layanan" name="layanan" required></select>
        </div>
        <div class="col-12">
            <label for="weight" class="form-label">Berat (gram)</label>
            <input type="number" class="form-control" id="weight" name="weight" value="<?php echo $total_weight; ?>" readonly>
        </div>
        <div class="col-12">
            <label for="jenis_pengiriman" class="form-label">Jenis Pengiriman</label>
            <select class="form-control" id="jenis_pengiriman" name="jenis_pengiriman" required>
                <option value="reguler">Reguler</option>
                <option value="express">Express</option>
                <option value="cargo">Kargo</option>
            </select>
        </div>
        <div class="col-12">
            <label for="ongkir" class="form-label">Ongkir</label>
            <input type="text" class="form-control" id="ongkir" name="ongkir" readonly>
        </div>
        <div class="col-12">
            <label for="no_whatsapp" class="form-label">Nomor WhatsApp</label>
            <input type="text" class="form-control" id="no_whatsapp" name="no_whatsapp" placeholder="contoh: 08123456789" required>
            <small class="form-text text-muted">Detail pembelian akan dikirim ke nomor WhatsApp ini</small>
        </div>
    </div>
    <div class="col-lg-6">
        <!-- Vertical Form -->
        <div class="col-12">
            <!-- Default Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Harga</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Sub Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    if (!empty($items)) :
                        foreach ($items as $index => $item) :
                    ?>
                            <tr>
                                <td><?php echo $item['name'] ?></td>
                                <td><?php echo number_to_currency($item['price'], 'IDR') ?></td>
                                <td><?php echo $item['qty'] ?></td>
                                <td><?php echo number_to_currency($item['price'] * $item['qty'], 'IDR') ?></td>
                            </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                    <tr>
                        <td colspan="2"></td>
                        <td>Subtotal</td>
                        <td><?php echo number_to_currency($total, 'IDR') ?></td>
                    </tr>
                    <tr>
                        <td colspan="2"></td>
                        <td>Total</td>
                        <td><span id="total"><?php echo number_to_currency($total, 'IDR') ?></span></td>
                    </tr>
                </tbody>
            </table>
            <!-- End Default Table Example -->
        </div>
        <div class="text-center">
            <button type="submit" class="btn btn-primary">Buat Pesanan</button>
        </div>
        </form><!-- Vertical Form -->
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('script') ?>
<script>
    $(document).ready(function() {
        var ongkir = 0;
        var total = 0;
        var weight = <?php echo $total_weight; ?>; // Get weight from PHP
        hitungTotal();

        $('#kelurahan').select2({
            placeholder: 'Ketik nama kelurahan...',
            ajax: {
                url: '<?= base_url('get-location') ?>',
                dataType: 'json',
                delay: 1500,
                data: function(params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.id,
                                text: item.subdistrict_name + ", " + item.district_name + ", " + item.city_name + ", " + item.province_name + ", " + item.zip_code
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 3
        });

        $("#kelurahan, #courier").on('change', function() {
            var id_kelurahan = $("#kelurahan").val();
            var courier = $("#courier").val();

            // Reset layanan dan ongkir
            $("#layanan").empty().append('<option value="">Pilih Layanan</option>');
            ongkir = 0;

            // Pastikan kedua field sudah dipilih
            if (id_kelurahan && courier) {
                $.ajax({
                    url: "<?= site_url('get-cost') ?>",
                    type: 'GET',
                    data: {
                        'destination': id_kelurahan,
                        'weight': weight,
                        'courier': courier
                    },
                    dataType: 'json',
                    success: function(data) {
                        data.forEach(function(item) {
                            var text = item["description"] + " (" + item["service"] + ") : estimasi " + item["etd"] + "";
                            $("#layanan").append($('<option>', {
                                value: item["cost"],
                                text: text
                            }));
                        });
                        hitungTotal();
                    },
                    error: function() {
                        alert('Gagal memuat data ongkir. Silakan coba lagi.');
                    }
                });
            } else {
                hitungTotal();
            }
        });

        $("#layanan").on('change', function() {
            ongkir = parseInt($(this).val());
            hitungTotal();
        });

        function hitungTotal() {
            total = ongkir + <?= $total ?>;

            $("#ongkir").val(ongkir);
            $("#total").html("IDR " + total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,'));
            $("#total_harga").val(total);
        }
    });
</script>
<?= $this->endSection() ?>