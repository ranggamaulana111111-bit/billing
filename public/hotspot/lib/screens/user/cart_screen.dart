import 'package:flutter/material.dart';
import 'checkout_screen.dart';
import '../../database/db_helper.dart';

class CartScreen extends StatefulWidget {
  final int userId;
  CartScreen({required this.userId});

  @override
  _CartScreenState createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final dbHelper = DbHelper();
  List<Map<String, dynamic>> _cartItems = [];
  double _totalPrice = 0.0;

  @override
  void initState() {
    super.initState();
    _loadCart();
  }

  void _loadCart() async {
    final data = await dbHelper.getCartItems(widget.userId);
    double total = 0.0;
    for (var item in data) {
      total += (item['price'] * item['quantity']);
    }
    setState(() {
      _cartItems = data;
      _totalPrice = total;
    });
  }

  void _checkout() async {
    if (_cartItems.isEmpty) return;

      Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutScreen(
        userId: widget.userId,
        totalBayar: _totalPrice,
        items: _cartItems,
      )
    )
  ).then((value) {
     if(value == true) _loadCart(); 
    }
  );

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Konfirmasi Pembayaran'),
        content: Text('Total tagihan Anda adalah Rp $_totalPrice. Lakukan pembayaran?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              await dbHelper.clearCart(widget.userId);
              Navigator.pop(context);
              _loadCart();
              showDialog(
                context: context,
                builder: (_) => AlertDialog(
                  title: Text('Sukses'),
                  content: Text('Pembayaran Berhasil! Terima kasih sudah berbelanja.'),
                  actions: [TextButton(onPressed: () => Navigator.pop(context), child: Text('OK'))],
                ),
              );
            },
            child: Text('Bayar'),
          )
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Keranjang Belanja')),
      body: _cartItems.isEmpty
          ? Center(child: Text('Keranjang Anda kosong.'))
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    itemCount: _cartItems.length,
                    itemBuilder: (context, index) {
                      final item = _cartItems[index];
                      return Card(
                        margin: EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                        child: ListTile(
                          leading: Image.network(item['image_url'], width: 50, height: 50, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(Icons.shopping_bag)),
                          title: Text(item['name']),
                          subtitle: Text('Rp ${item['price']} x ${item['quantity']}'),
                          trailing: Text('Rp ${item['price'] * item['quantity']}', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      );
                    },
                  ),
                ),
                Container(
                  padding: EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Colors.grey.withOpacity(0.3), spreadRadius: 2, blurRadius: 5)]),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Total: Rp $_totalPrice', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      ElevatedButton(
                        onPressed: _checkout,
                        child: Text('Checkout', style: TextStyle(fontSize: 16)),
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.green, padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12)),
                      )
                    ],
                  ),
                )
              ],
            ),
    );
  }
}