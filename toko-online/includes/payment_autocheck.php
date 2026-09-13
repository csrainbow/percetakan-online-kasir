<?php
// ============================================
// PAYMENT AUTO-CHECK — Jalur B (self-hosted)
// 1) Nominal unik per pesanan (unique_amount)
// 2) Notifikasi mutasi masuk (API / paste admin / cron)
// 3) Pencocokan otomatis nominal -> tandai lunas + notifikasi
// ============================================

// 🔥 Kunci proses agar tidak berjalan dobel (cron + web)
function pm_lockfile() {
    $dir = sys_get_temp_dir();
    return $dir . '/rainbow_pm_' . md5(__DIR__) . '.lock';
}
function pm_lock() {
    $f = fopen(pm_lockfile(), 'c');
    if (!$f) return false;
    if (!flock($f, LOCK_EX | LOCK_NB)) {
        fclose($f);
        return false;
    }
    return $f;
}
function pm_unlock($f) {
    if ($f) {
        flock($f, LOCK_UN);
        fclose($f);
        @unlink(pm_lockfile());
    }
}

// 🔥 Generate kode unik 3 digit (100-999) yang tidak sedang dipakai order aktif
function paygen_unique_code($db) {
    $used = [];
    try {
        $rows = $db->query("SELECT pay_code FROM orders WHERE payment_status IN ('unpaid','dp') AND pay_code > 0")
                   ->fetchAll();
        foreach ($rows as $r) {
            $used[(int)$r['pay_code']] = true;
        }
    } catch (Exception $e) { /* kolom belum ada */ }
    for ($i = 0; $i < 300; $i++) {
        $c = random_int(100, 999);
        if (!isset($used[$c])) return $c;
    }
    return 0;
}

// QRIS statis tidak membebankan biaya penyedia layanan

function qris_fee_percent() {
    return 0;
}

// 🔥 Format persen untuk tampilan (0.7 -> "0,7%", 1.0 -> "1%")
function pm_pct_str($pct) {
    $pct = (float)$pct;
    $rounded = round($pct, 1);
    return floor($rounded) == $rounded
        ? number_format($rounded, 0, ',', '.') . '%'
        : number_format($rounded, 1, ',', '.') . '%';
}

// 🔥 Hitung breakdown nominal unik: Total + kode unik
function pay_breakdown($order) {
    $total = (int)round((float)($order['total'] ?? 0));
    $unique = (int)($order['unique_amount'] ?? 0);
    $code = (int)($order['pay_code'] ?? 0);
    $fee = (int)($order['service_fee'] ?? 0);
    $pct = ($total > 0 && $fee > 0) ? ($fee / $total) * 100 : qris_fee_percent();
    return [
        'total' => $total,
        'fee_pct' => $pct,
        'fee' => $fee,
        'base' => $total + $fee,
        'code' => $code,
        'unique' => $unique,
    ];
}

// 🔥 Tempelkan nomor unik ke order (panggil setelah order disimpan)
// Nominal unik untuk semua pembayaran manual = total + kode unik
function pay_attach_order($db, $orderId, $total, $method = 'transfer') {
    $total = (int)round((float)$total);
    if ($total <= 0) return 0;
    $code = paygen_unique_code($db);
    $fee = 0;
    $base = $total + $fee;
    $unique = $code > 0 ? $base + $code : $base;
    $db->prepare("UPDATE orders SET pay_code=?, unique_amount=?, service_fee=? WHERE id=?")
       ->execute([$code, $unique, $fee, $orderId]);
    return $unique;
}

// 🔥 Ukuran unik untuk order (untuk tampilan)
function pay_info($order) {
    $total = (int)round((float)($order['total'] ?? 0));
    $unique = (int)($order['unique_amount'] ?? 0);
    $code = (int)($order['pay_code'] ?? 0);
    return ['total' => $total, 'unique' => $unique, 'code' => $code];
}

// 🔥 Parse teks mutasi / notifikasi e-banking jadi daftar hit
function pm_parse_text($text) {
    $hits = [];
    if ($text === '' || $text === null) return $hits;
    $lines = preg_split('/\r\n|\r|\n/', $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        // nominal: "Rp 1.234.567" / "1,234,567" / "158317"
        $amount = 0;
        $m = null;
        // prioritaas: format ribuan "Rp 1.234.567" / "750.318", baru angka polos panjang
        if (preg_match('~(?:(?:Rp|IDR)\.?\s*)?(\d{1,3}(?:[.,]\d{3})+)~i', $line, $m)) {
            $amount = (int)preg_replace('/[^0-9]/', '', $m[1]);
        }
        if ($amount === 0 && preg_match('~\b(\d{4,})\b~', $line, $m)) {
            $amount = (int)$m[1];
        }
        if ($amount < 1000) continue; // terlalu kecil, bukan transfer pesanan
        // tanggal: 31/12/2026 | 31-12-2026 | 31 Dec 2026
        $txdate = '';
        if (preg_match('~\b(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})\b~', $line, $m2)) {
            $txdate = sprintf("%04d-%02d-%02d", $m2[3], $m2[2], $m2[1]);
        } elseif (preg_match('~\b(\d{1,2})[-/.\s]+(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des|Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember|Ene|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec\.?)[-/.\s]+(\d{2,4})\b~i', $line, $m2)) {
            $mon = strtolower($m2[2]);
            $map = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'mei' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'agu' => 8, 'sep' => 9, 'oct' => 10, 'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12];
            $yy = (int)$m2[3];
            if ($yy < 100) $yy += 2000;
            $txdate = sprintf("%04d-%02d-%02d", $yy, $map[$mon] ?? 1, (int)$m2[1]);
        } elseif (preg_match('~\b(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})\s+(\d{1,2}:\d{2})\b|(\d{1,2}:\d{2})\s+(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})\b~', $line, $m3)) {
            $txdate = '';
        }
        // ref number (angka panjang >= 8 digit)
        $refno = '';
        if (preg_match('/(?:Ref|No\.?|No)\s*[:.]?\s*(\d{8,})/i', $line, $m4)) {
            $refno = $m4[1];
        }
        // bank
        $bank = '';
        foreach (['BCA','Mandiri','BRI','BNI','BNI Syariah','BTN','Permata','CIMB','BNI'] as $b) {
            if (stripos($line, $b) !== false) { $bank = $b; break; }
        }
        // deskripsi: sisa baris tanpa nominal/tanggal
        $desc = preg_replace('~(?:(?:Rp|IDR)\.?\s*)?\d{1,3}(?:[.,]\d{3})+|\b\d{4,}\b~i', '', $line);
        $desc = preg_replace('~\d{1,2}[-/.]\d{1,2}[-/.]\d{2,4}~', '', $desc);
        $desc = trim(preg_replace('/\s+/', ' ', $desc));
        $hits[] = [
            'amount' => $amount,
            'txdate' => $txdate,
            'refno' => $refno,
            'bank_name' => $bank,
            'description' => mb_substr($desc, 0, 190),
        ];
    }
    return $hits;
}

// 🔥 Simpan hit ke payment_hits (dengan dedup)
function pm_insert_hits($db, $hits, $source = 'manual') {
    $inserted = 0;
    $st = $db->prepare("INSERT INTO payment_hits (amount, txdate, refno, description, bank_name, status, source) VALUES (?, ?, ?, ?, ?, 'new', ?)");
    foreach ($hits as $h) {
        $dup = false;
        $a = (int)$h['amount'];
        $ref = trim($h['refno'] ?? '');
        if ($ref !== '') {
            $q = $db->prepare("SELECT id FROM payment_hits WHERE refno=? AND amount=?");
            $q->execute([$ref, $a]);
            $dup = (bool)$q->fetch();
        }
        if (!$dup) {
            $q = $db->prepare("SELECT id FROM payment_hits WHERE amount=? AND txdate=? AND status IN ('matched','ambiguous','duplicate')");
            $q->execute([$a, $h['txdate'] ?? '']);
            if ($q->fetch()) $dup = true;
        }
        if ($dup) continue;
        $st->execute([
            $a,
            $h['txdate'] ?? '',
            $ref,
            $h['description'] ?? '',
            $h['bank_name'] ?? '',
            $source,
        ]);
        $inserted++;
    }
    return $inserted;
}

// 🔥 Proses hit 'new' -> cocokkan ke order -> tandai lunas
function pm_match_hits($db, &$log = [], $limit = 50) {
    $rows = $db->query("SELECT * FROM payment_hits WHERE status='new' ORDER BY id ASC LIMIT " . (int)$limit)
               ->fetchAll();
    if (!$rows) {
        return ['matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'duplicate' => 0, 'processed' => 0];
    }
    $sum = ['matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'duplicate' => 0, 'processed' => count($rows)];
    // Cocokkan nominal unik (total + kode) atau total persis untuk transfer lama.
    $find = $db->prepare("SELECT * FROM orders WHERE payment_status IN ('unpaid','dp') AND payment_method IN ('transfer','qris','qris_dinamis') AND total > 0 AND (unique_amount = ? OR (total = ? AND payment_method = 'transfer') OR (total + COALESCE(service_fee,0) = ? AND payment_method IN ('qris','qris_dinamis')))");

    foreach ($rows as $h) {
        $amount = (int)$h['amount'];
        $find->execute([$amount, $amount, $amount]);
        $rows2 = $find->fetchAll();
        $cands = [];
        $seen = [];
        foreach ($rows2 as $o) {
            if (isset($seen[$o['id']])) continue;
            $seen[$o['id']] = true;
            $cands[] = $o;
        }
        $n = count($cands);
        $hitSt = $db->prepare("UPDATE payment_hits SET status=?, matched_order_id=?, note=? WHERE id=?");

        if ($n === 0) {
            $hitSt->execute(['unmatched', 0, 'Nominal tidak cocok dengan order aktif.', $h['id']]);
            $sum['unmatched']++;
        } elseif ($n > 1) {
            $hitSt->execute(['ambiguous', 0, 'Nominal sama untuk ' . $n . ' order. Cek manual.', $h['id']]);
            $sum['ambiguous']++;
        } else {
            $order = $cands[0];
            $alreadyPaid = ($order['payment_status'] ?? '') === 'paid';
            if ($alreadyPaid) {
                $hitSt->execute(['duplicate', $order['id'], 'Order sudah lunas.', $h['id']]);
                $sum['duplicate']++;
                continue;
            }
            // Mark paid
            $db->prepare("UPDATE orders SET payment_status='paid', paid_at=datetime('now') WHERE id=?")
               ->execute([$order['id']]);
            $db->prepare("INSERT INTO payments (order_id, bank_name, account_number, account_name, amount, proof_image, payment_type, status, created_at) VALUES (?, ?, '', 'AUTO', ?, 'auto', 'lunas', 'verified', COALESCE(?, datetime('now')))")
               ->execute([$order['id'], $h['bank_name'] ?: '-', $amount, $h['txdate'] ?: null]);
            $hitSt->execute(['matched', $order['id'], 'Nominal cocok. Otomatis lunas.', $h['id']]);
            $sum['matched']++;
            $log[] = "Pesanan {$order['order_code']} LUNAS otomatis (nominal {$amount}).";

            // Notifikasi email admin
            $code = $order['order_code'];
            $total = 'Rp ' . number_format($order['total'] ?? 0, 0, ',', '.');
            $adminEmail = getSetting('admin_email');
            if ($adminEmail && function_exists('sendEmail')) {
                @sendEmail(
                    $adminEmail,
                    "✅ Pembayaran Otomatis Diterima — $code",
                    "Pembayaran pesanan $code ($order[customer_name]) terdeteksi otomatis.\n"
                    . "Nominal masuk: Rp " . number_format($amount, 0, ',', '.') . "\n"
                    . "Bank: " . ($h['bank_name'] ?: '-') . " | Tanggal: " . ($h['txdate'] ?: '-') . "\n"
                    . "Total pesanan: $total\n"
                    . "Status: LUNAS (dikonfirmasi otomatis)\n"
                    . "Link: https://rainbowprinting.web.id/admin/order-detail.php?id={$order['id']}"
                );
                if (function_exists('wa_web_notify_admin')) {
                    wa_web_notify_admin("✅ Pembayaran Otomatis (Web) - " . $code, [
                        "Nominal masuk: Rp " . number_format($amount, 0, ',', '.'),
                        "Bank: " . ($h['bank_name'] ?: '-'),
                        "Total pesanan: " . $total,
                        "Link: https://rainbowprinting.web.id/admin/order-detail.php?id={$order['id']}",
                    ]);
                }
            }
        }
    }
    return $sum;
}

// 🔥 Jalur utama: lock -> insert (opsional) -> match -> unlock
function pm_run($source = 'manual', $pendingHits = []) {
    global $db;
    $log = [];
    $lk = pm_lock();
    if (!$lk) {
        return ['locked' => true, 'summary' => null, 'log' => ['Proses sedang berjalan (terkunci).']];
    }
    try {
        if (!empty($pendingHits)) {
            pm_insert_hits($db, $pendingHits, $source);
        }
        $summary = pm_match_hits($db, $log);
    } finally {
        pm_unlock($lk);
    }
    return ['locked' => false, 'summary' => $summary, 'log' => $log];
}