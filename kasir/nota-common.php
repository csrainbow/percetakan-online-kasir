<?php
function nota_data($ref, $id) {
    if (!in_array($ref, ['pesanan', 'penjualan'])) {
        $ref = 'pesanan';
    }
    $id = (int)$id;
    $viewItems = [];
    $pembayaran = [];
    $totalBayar = 0.0;
    $ps = null;

    if ($ref === 'penjualan') {
        $row = DB::one('SELECT * FROM penjualan WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        $items = DB::q('SELECT nama, harga, qty, subtotal FROM penjualan_item WHERE penjualan_id = ? ORDER BY id', [$id]);
        $viewItems = array_map(function ($i) {
            return ['nama' => $i['nama'], 'qty' => qty($i['qty']), 'harga' => rp($i['harga']), 'total' => rp($i['subtotal'])];
        }, $items);
        $ps = [
            'no_pesanan' => $row['no_invoice'],
            'tgl' => $row['tgl'],
            'pelanggan' => $row['keterangan'] ?: 'Umum (Tunai)',
            'telepon' => '',
            'deskripsi' => implode("\n", array_map(function ($i) {
                return $i['nama'] . ' x' . qty($i['qty']);
            }, $items)),
            'total' => (float)$row['total'],
            'dp' => (float)$row['total'],
            'sisa' => 0.0,
            'status' => 'Lunas',
            'pembayaran_status' => 'Lunas',
            'user_id' => $row['user_id'],
        ];
        $totalBayar = (float)$row['total'];
    } else {
        $row = DB::one('SELECT * FROM pesanan WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        $pembayaran = DB::q("SELECT * FROM pembayaran WHERE ref_type = 'pesanan' AND ref_id = ? ORDER BY id", [$id]);
        $ps = $row;
        $ps['total'] = (float)$ps['total'];
        $ps['dp'] = (float)$ps['dp'];
        $ps['sisa'] = max(0, (float)$ps['sisa']);
        $totalBayar = max(0, $ps['total'] - $ps['sisa']);
        $ps['pembayaran_status'] = pembayaran_status_label($totalBayar, $ps['total'], $ps['status']);
        $pitems = DB::q('SELECT nama, qty, harga, subtotal FROM pesanan_item WHERE pesanan_id = ? ORDER BY id', [$id]);
        if ($pitems) {
            $viewItems = array_map(function ($i) {
                return ['nama' => $i['nama'], 'qty' => qty($i['qty']), 'harga' => rp($i['harga']), 'total' => rp($i['subtotal'])];
            }, $pitems);
        } else {
            $lines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $ps['deskripsi'])))));
            if (!$lines) {
                $lines = ['Pesanan'];
            }
            $viewItems = [[
                'nama' => array_shift($lines),
                'note' => $lines,
                'qty' => 1,
                'harga' => rp($ps['total']),
                'total' => rp($ps['total']),
            ]];
        }
    }

    return [$ref, $ps, $viewItems, $pembayaran, $totalBayar];
}
