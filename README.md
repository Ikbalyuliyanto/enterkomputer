http://localhost/testapi/api.php?produk => untuk menampilkan produk method -GET.
http://localhost/testapi/api.php?printer => untuk menampilkan printer method -GET.
http://localhost/testapi/api.php?meja => untuk menampilkan meja method -GET.
http://localhost/testapi/api.php?promo => untuk menampilkan promo method -GET.
http://localhost/testapi/api.php => untuk menambah pesanan method -POST.
{
    "meja_id": 1,
    "pesanan": [
        { "produk_id": 1, "jumlah": 2 },
        { "produk_id": 6, "jumlah": 1 },
        { "promo_id": 1, "jumlah": 2 },
        { "produk_id": 3, "jumlah": 1 },
        { "produk_id": 8, "jumlah": 1 }
    ]
}
http://localhost/testapi/api.php?pesanan_id=1 => untuk menampilkan pesanan method -GET.
