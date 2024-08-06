<?php
header("Content-Type: application/json");

// Koneksi database
$host = "localhost"; // Port default MySQL adalah 3306
$db_name = "enterkomputer";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Connection error: " . $e->getMessage()]);
    die();
}

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$metode = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($metode) {
    case 'POST':
        
    $meja_id = $input['meja_id'];

        $stmt = $pdo->prepare("SELECT p.meja_id FROM pesanan p WHERE p.meja_id = ?");
        $stmt->execute([$meja_id]);
        $info_isimeja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($info_isimeja) {
            $isi_meja = 'Meja Sudah dipesan';
        } else {
            $isi_meja = 'Pesanan dibuat';
        }

        if (isset($input['pesanan']) && isset($input['meja_id'])) {
            $meja_id = $input['meja_id'];
            $item_pesanan = $input['pesanan'];
    
            // Buat pesanan
            $stmt = $pdo->prepare("INSERT INTO pesanan (meja_id) VALUES (?)");
            $stmt->execute([$meja_id]);
            $pesanan_id = $pdo->lastInsertId();
    
            $printer_ids = [];
            $items = [];
    
            // Masukkan item pesanan dan kumpulkan id printer
            foreach ($item_pesanan as $item) {
                if (isset($item['produk_id'])) {
                    $produk_id = $item['produk_id'];
                    $jumlah = $item['jumlah'];
    
                    $stmt = $pdo->prepare("INSERT INTO item_pesanan (pesanan_id, produk_id, jumlah) VALUES (?, ?, ?)");
                    $stmt->execute([$pesanan_id, $produk_id, $jumlah]);
    
                    // Dapatkan nama produk, varian, dan kategori_id
                    $stmt = $pdo->prepare("SELECT p.nama AS nama_produk, p.varian, p.kategori_id FROM produk p WHERE p.id = ?");
                    $stmt->execute([$produk_id]);
                    $info_produk = $stmt->fetch(PDO::FETCH_ASSOC);
    
                    // Dapatkan printer_id untuk kategori
                    $stmt = $pdo->prepare("SELECT printer_id FROM penugasan_printer WHERE kategori_id = ?");
                    $stmt->execute([$info_produk['kategori_id']]);
                    $printer_id = $stmt->fetchColumn();
    
                    if ($printer_id) {
                        $printer_ids[$printer_id] = true;
                    }
    
                    // Simpan informasi item pesanan
                    $items[] = [
                        'nama' => $info_produk['nama_produk'],
                        'varian' => $info_produk['varian'],
                        'jumlah' => $jumlah,
                        'printer_id' => $printer_id // Tambahkan informasi printer_id
                    ];
                } else if (isset($item['promo_id'])) {
                    $promo_id = $item['promo_id'];
                    $jumlah = $item['jumlah'];
    
                    $stmt = $pdo->prepare("INSERT INTO item_pesanan (pesanan_id, promo_id, jumlah) VALUES (?, ?, ?)");
                    $stmt->execute([$pesanan_id, $promo_id, $jumlah]);
    
                    // Dapatkan nama promo
                    $stmt = $pdo->prepare("SELECT nama AS nama_promo FROM promo WHERE id = ?");
                    $stmt->execute([$promo_id]);
                    $info_promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
                    // Dapatkan semua printer_id untuk kategori promo (misalnya kategori dengan ID 3)
                    $stmt = $pdo->prepare("SELECT printer_id FROM penugasan_printer WHERE kategori_id != 3");
                    $stmt->execute();
                    $printer_ids_promo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
                    foreach ($printer_ids_promo as $printer_id) {
                        $printer_ids[$printer_id] = true;
                    }
    
                    // Simpan informasi promo
                    $items[] = [
                        'nama' => $info_promo['nama_promo'],
                        'jumlah' => $jumlah,
                        'printer_ids' => $printer_ids_promo // Tambahkan informasi printer_ids untuk promo
                    ];
                }
            }
    
            // Dapatkan id printer yang unik
            $printer_ids = array_keys($printer_ids);
    
            // Dapatkan nama printer
            $stmt = $pdo->prepare("SELECT id, nama FROM printer WHERE id IN (" . implode(',', $printer_ids) . ")");
            $stmt->execute();
            $printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Buat array untuk menghubungkan printer dengan kategori
            $printer_map = [];
            foreach ($printers as $printer) {
                $printer_map[$printer['id']] = $printer['nama'];
            }
    
            // Tampilkan hasil JSON
            echo json_encode([
                'status' => $isi_meja,
                'pesanan_id' => $pesanan_id,
                'items' => array_map(function ($item) use ($printer_map) {
                    return [
                        'nama' => $item['nama'],
                        'varian' => $item['varian'] ?? null,
                        'jumlah' => $item['jumlah'],
                        'printers' => isset($item['printer_ids']) ? array_map(function ($id) use ($printer_map) {
                            return $printer_map[$id] ?? 'Tidak diketahui';
                        }, $item['printer_ids']) : ($printer_map[$item['printer_id']] ?? 'Tidak diketahui')
                    ];
                }, $items)
            ]);
        } else {
            echo json_encode(['status' => 'Data pesanan atau meja_id tidak ditemukan']);
        }
    break;    

    case 'GET':
        if (isset($_GET['pesanan_id'])) {
            $pesanan_id = $_GET['pesanan_id'];
    
            // Ambil informasi pesanan
            $stmt = $pdo->prepare("SELECT pesanan.id, meja.nama AS nama_meja, pesanan.dibuat_pada FROM pesanan JOIN meja ON pesanan.meja_id = meja.id WHERE pesanan.id = ?");
            $stmt->execute([$pesanan_id]);
            $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($pesanan) {
                // Ambil item produk dengan harga
                $stmt = $pdo->prepare("SELECT produk.nama, produk.varian, item_pesanan.jumlah, produk.harga FROM item_pesanan JOIN produk ON item_pesanan.produk_id = produk.id WHERE item_pesanan.pesanan_id = ?");
                $stmt->execute([$pesanan_id]);
                $item_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                // Ambil item promo dengan harga
                $stmt = $pdo->prepare("SELECT promo.nama AS nama_promo, item_pesanan.jumlah, promo.harga FROM item_pesanan JOIN promo ON item_pesanan.promo_id = promo.id WHERE item_pesanan.pesanan_id = ?");
                $stmt->execute([$pesanan_id]);
                $item_promo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                // Gabungkan item produk dan promo
                $items = array_merge($item_pesanan, $item_promo);
                $total_harga = 0;
    
                foreach ($items as &$item) {
                    // Tambahkan harga dan total harga
                    if (isset($item['harga'])) {
                        $total_harga += $item['harga'] * $item['jumlah'];
                    }

                unset($item['harga']);
                }
    
                // Tambahkan item ke dalam hasil pesanan
                $pesanan['item'] = $items;
                $pesanan['total_harga'] = $total_harga;
    
                echo json_encode($pesanan);
            } else {
                echo json_encode(['status' => 'Pesanan tidak ditemukan']);
            }
        } else if (isset($_GET['meja_id'])) {
            // Tambahkan endpoint untuk mendapatkan jumlah pesanan dan detail pesanan di meja tertentu
            $meja_id = $_GET['meja_id'];
            
            // Dapatkan jumlah pesanan di meja tertentu
            $stmt = $pdo->prepare("SELECT COUNT(*) as jumlah_pesanan FROM pesanan WHERE meja_id = ?");
            $stmt->execute([$meja_id]);
            $jumlah_pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Dapatkan detail pesanan di meja tertentu
            $stmt = $pdo->prepare("SELECT pesanan.id, pesanan.dibuat_pada FROM pesanan WHERE meja_id = ?");
            $stmt->execute([$meja_id]);
            $detail_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detail_pesanan as &$pesanan) {
                $pesanan_id = $pesanan['id'];

                // Ambil item produk
                $stmt = $pdo->prepare("SELECT produk.nama, produk.varian, item_pesanan.jumlah, produk.harga FROM item_pesanan JOIN produk ON item_pesanan.produk_id = produk.id WHERE item_pesanan.pesanan_id = ?");
                $stmt->execute([$pesanan_id]);
                $item_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Ambil item promo
                $stmt = $pdo->prepare("SELECT promo.nama AS nama_promo, item_pesanan.jumlah, promo.harga FROM item_pesanan JOIN promo ON item_pesanan.promo_id = promo.id WHERE item_pesanan.pesanan_id = ?");
                $stmt->execute([$pesanan_id]);
                $item_promo = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Gabungkan item produk dan promo
                $items = array_merge($item_pesanan, $item_promo);
                $total_harga = 0;
    
                foreach ($items as &$item) {
                    if (isset($item['harga'])) {
                        $total_harga += $item['harga'] * $item['jumlah'];
                    }
                    unset($item['harga']);
                }

                $pesanan['items'] = $items;
                $pesanan['total_harga'] = $total_harga;
            }

            echo json_encode([
                'jumlah_pesanan' => $jumlah_pesanan['jumlah_pesanan'],
                'detail_pesanan' => $detail_pesanan
            ]);
        } else if (isset($_GET['produk'])) {
            // Tambahkan endpoint untuk mendapatkan data produk
            $stmt = $pdo->prepare("SELECT p.id, p.nama, p.varian, p.harga, c.nama AS kategori 
                                   FROM produk p 
                                   JOIN kategori c ON p.kategori_id = c.id");
            $stmt->execute();
            $produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($produk);
        } else if (isset($_GET['meja'])) {
            // Tambahkan endpoint untuk mendapatkan data meja
            $stmt = $pdo->prepare("SELECT id, nama FROM meja");
            $stmt->execute();
            $meja = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($meja);
        } else if (isset($_GET['printer'])) {
            // Tambahkan endpoint untuk mendapatkan data printer
            $stmt = $pdo->prepare("SELECT id, nama FROM printer");
            $stmt->execute();
            $printer = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($printer);
        } else if (isset($_GET['promo'])) {
            // Tambahkan endpoint untuk mendapatkan data promo
            $stmt = $pdo->prepare("SELECT id, nama, harga FROM promo");
            $stmt->execute();
            $promo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($promo);
        } else {
            echo json_encode(['status' => 'Permintaan tidak valid']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'Metode Tidak Diizinkan']);
        break;
}
?>
