import 'package:flutter/material.dart';
import '../../database/db_helper.dart';

class CheckoutScreen extends StatefulWidget {
  final int userId;
  final double totalBayar;
  final List<Map<String, dynamic>> items;

  CheckoutScreen({required this.userId, required this.totalBayar, required this.items});

  @override
  _CheckoutScreenState createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _selectedMethod = 'Transfer Bank';
  final dbHelper = DbHelper();

  void _prosesPembayaran() async {
    // 1. Kosongkan keranjang di database lokal
    await dbHelper.clearCart(widget.userId);

    // 2. Tampilkan dialog sukses
    showDialog(
      context: context,
      barrierDismissible: false, // User wajib klik OK
      builder: (context) => AlertDialog(
        icon: Icon(Icons.check_circle, color: Colors.green, size: 60),
        title: Text('Transaksi Sukses!'),
        content: Text('Pembayaran sebesar Rp ${widget.totalBayar} via $_selectedMethod berhasil diverifikasi.\n\nPesanan sedang dikemas!'),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context); // Tutup dialog
              Navigator.pop(context, true); // Kembali ke CartScreen dengan status 'true' agar cart refresh jadi kosong
            },
            child: Text('Kembali ke Beranda'),
          )
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Metode Pembayaran')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Card(
              color: Colors.blue[50],
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Total Tagihan:', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    Text('Rp ${widget.totalBayar}', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.red[700])),
                  ],
                ),
              ),
            ),
            SizedBox(height: 20),
            Text('Pilih Metode Pembayaran:', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            SizedBox(height: 10),
            RadioListTile<String>(
              title: Text('Transfer Bank (Virtual Account)'),
              value: 'Transfer Bank',
              groupValue: _selectedMethod,
              onChanged: (val) => setState(() => _selectedMethod = val!),
            ),
            RadioListTile<String>(
              title: Text('E-Wallet (Dana / ShopeePay / OVO)'),
              value: 'E-Wallet',
              groupValue: _selectedMethod,
              onChanged: (val) => setState(() => _selectedMethod = val!),
            ),
            RadioListTile<String>(
              title: Text('Cash On Delivery (COD)'),
              value: 'COD',
              groupValue: _selectedMethod,
              onChanged: (val) => setState(() => _selectedMethod = val!),
            ),
            Spacer(),
            ElevatedButton(
              onPressed: _prosesPembayaran,
              child: Text('BAYAR SEKARANG', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                minimumSize: Size(double.infinity, 55),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
              ),
            )
          ],
        ),
      ),
    );
  }
}