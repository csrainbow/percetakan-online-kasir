<?php
function rekap_data($tgl, $userF = 0) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$tgl)) {
        $tgl = date('Y-m-d');
    }
    $extraP = '';
    $extraPe = '';
    $extraPay = '';
    if ((int)$userF > 0) {
        $extraP = ' AND p.user_id = ' . (int)$userF;
        $extraPe = ' AND pe.user_id = ' . (int)$userF;
        $extraPay = ' AND pp.user_id = ' . (int)$userF;
    }
    $scPay = scope_user_id() > 0 ? 'pp.user_id = ' . scope_user_id() : '1=1';

    $sumPenjualan = DB::one("SELECT COALESCE(SUM(p.total),0) total, COUNT(*) c FROM penjualan p WHERE date(p.tgl) = ? AND " . scope_sql('p') . "$extraP", [$tgl]);
    $perMetode = DB::q("SELECT p.metode, COUNT(*) c, COALESCE(SUM(p.total),0) t FROM penjualan p WHERE date(p.tgl) = ? AND " . scope_sql('p') . "$extraP GROUP BY p.metode", [$tgl]);
    $penjualan = DB::q("SELECT p.id, p.no_invoice, p.tgl, p.metode, p.total, p.bayar, p.kembalian, u.username
                        FROM penjualan p LEFT JOIN users u ON u.id = p.user_id
                        WHERE date(p.tgl) = ? AND " . scope_sql('p') . "$extraP ORDER BY p.id", [$tgl]);
    $pembayaran = DB::q("SELECT pp.tgl, pp.jumlah, pp.metode, pe.no_pesanan, pe.pelanggan
                         FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                         WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) = ? AND $scPay$extraPay ORDER BY pp.id", [$tgl]);
    $sumPembayaran = DB::one("SELECT COALESCE(SUM(pp.jumlah),0) total, COUNT(*) c FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                              WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) = ? AND $scPay$extraPay", [$tgl]);
    $terimaKasir = DB::one("SELECT COALESCE(SUM(pp.jumlah),0) t FROM pembayaran pp JOIN pesanan pe ON pe.id = pp.ref_id
                            WHERE pp.ref_type = 'pesanan' AND date(pp.tgl) = ?
                            AND (pp.keterangan IS NULL OR pp.keterangan NOT LIKE '%via kasir%') AND $scPay$extraPay", [$tgl]);
    $hpp = DB::one("SELECT COALESCE(SUM(pr.harga_beli * i.qty),0) h
                    FROM penjualan_item i
                    JOIN penjualan p ON p.id = i.penjualan_id
                    JOIN produk pr ON pr.id = i.produk_id
                    WHERE date(p.tgl) = ? AND " . scope_sql('p') . "$extraP", [$tgl]);

    $pendapatan = (float)$sumPenjualan['total'] + (float)$terimaKasir['t'];
    $labaKotor = $pendapatan - (float)$hpp['h'];

    return [
        'tgl' => $tgl,
        'sumPenjualan' => $sumPenjualan,
        'perMetode' => $perMetode,
        'penjualan' => $penjualan,
        'pembayaran' => $pembayaran,
        'sumPembayaran' => $sumPembayaran,
        'terimaKasir' => (float)$terimaKasir['t'],
        'hpp' => (float)$hpp['h'],
        'pendapatan' => $pendapatan,
        'labaKotor' => $labaKotor,
    ];
}

function rekap_user_filter() {
    $userF = 0;
    if (is_superadmin() && scope_user_id() === 0) {
        $userF = (int)($_GET['user'] ?? 0);
    } elseif (scope_user_id() > 0) {
        $userF = scope_user_id();
    }
    return $userF;
}
