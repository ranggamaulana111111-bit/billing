import 'package:flutter/material.dart';
import '../../database/db_helper.dart';
import '../auth/login_screen.dart';
import 'cart_screen.dart';

class UserDashboard extends StatefulWidget {
  final int userId;
  UserDashboard({required this.userId});

  @override
  _UserDashboardState createState() => _UserDashboardState();
}

class _UserDashboardState extends State<UserDashboard> {
  final dbHelper = DbHelper();
  List<Map<String, dynamic>> _allProducts = [];
  List<Map<String, dynamic>> _featuredProducts = [];

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  void _loadProducts() async {
    final all = await dbHelper.getProducts();
    setState(() {
      _allProducts = all;
      _featuredProducts = all.where((element) => element['is_featured'] == 1).toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Smart Market'),
        actions: [
          IconButton(icon: Icon(Icons.shopping_cart), onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CartScreen(userId: widget.userId)))),
          IconButton(icon: Icon(Icons.logout), onPressed: () => Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => LoginScreen()))),
        ],
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('⭐ Produk Unggulan', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 10),

              Container(
                height: 180,
                child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: _featuredProducts.length,
                itemBuilder: (context, index) {
                final prod = _featuredProducts[index];
      
      
              return Card( 
                child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
            Image.network(
              prod['image_url'], 
              height: 90, 
              width: 140, 
              fit: BoxFit.cover, 
              errorBuilder: (_, __, ___) => const Icon(Icons.fastfood, size: 50),
            ),
            Padding(
              padding: const EdgeInsets.all(4.0),
              child: Text(
                prod['name'], 
                maxLines: 1, 
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4.0),
              child: Text('Rp ${prod['price']}'),
            ),
            const Spacer(),
            IconButton(
              icon: const Icon(Icons.add_shopping_cart, size: 20, color: Colors.green),
              onPressed: () async {
                await dbHelper.addToCart(widget.userId, prod['id']);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('${prod['name']} masuk keranjang!'),
                    duration: const Duration(seconds: 1),
                  ),
                );
              },
            ), 
          ],
        ), 
      ); 
    }, 
  ), 
),
              SizedBox(height: 20),
              Text('🛒 Semua Produk', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              ListView.builder(
                shrinkWrap: true,
                physics: NeverScrollableScrollPhysics(),
                itemCount: _allProducts.length,
                itemBuilder: (context, index) {
                  final prod = _allProducts[index];
                  return Card(
                    child: ListTile(
                      leading: Image.network(prod['image_url'], width: 50, height: 50, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(Icons.shopping_bag)),
                      title: Text(prod['name']),
                      subtitle: Text('Rp ${prod['price']}'),
                      trailing: IconButton(
                        icon: Icon(Icons.add_shopping_cart, color: Colors.green),
                        onPressed: () async {
                          await dbHelper.addToCart(widget.userId, prod['id']);
                          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${prod['name']} masuk keranjang!'), duration: Duration(seconds: 1)));
                        },
                      ),
                    ),
                  );
                },
              )
            ],
          ),
        ),
      ),
    );
  }
}