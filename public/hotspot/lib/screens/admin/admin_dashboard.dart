import 'package:flutter/material.dart';
import '../../database/db_helper.dart';
import '../auth/login_screen.dart';

class AdminDashboard extends StatefulWidget {
  @override
  _AdminDashboardState createState() => _AdminDashboardState();
}

class _AdminDashboardState extends State<AdminDashboard> {
  final dbHelper = DbHelper();
  List<Map<String, dynamic>> _products = [];

  @override
  void initState() {
    super.initState();
    _refreshProducts();
  }

  void _refreshProducts() async {
    final data = await dbHelper.getProducts();
    setState(() {
      _products = data;
    });
  }

  void _showForm(int? id) async {
    final existingProduct = id != null ? _products.firstWhere((element) => element['id'] == id) : null;
    final nameController = TextEditingController(text: existingProduct?['name'] ?? '');
    final priceController = TextEditingController(text: existingProduct?['price']?.toString() ?? '');
    final imageController = TextEditingController(text: existingProduct?['image_url'] ?? '');
    bool isFeatured = (existingProduct?['is_featured'] ?? 0) == 1;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, top: 16, left: 16, right: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameController, decoration: InputDecoration(labelText: 'Nama Produk')),
              TextField(controller: priceController, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Harga')),
              TextField(controller: imageController, decoration: InputDecoration(labelText: 'URL Gambar')),
              CheckboxListTile(
                title: Text('Jadikan Produk Unggulan'),
                value: isFeatured,
                onChanged: (val) => setModalState(() => isFeatured = val!),
              ),
              SizedBox(height: 16),
              ElevatedButton(
                onPressed: () async {
                  final data = {
                    'name': nameController.text,
                    'price': double.tryParse(priceController.text) ?? 0.0,
                    'image_url': imageController.text.isEmpty ? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=500' : imageController.text,
                    'is_featured': isFeatured ? 1 : 0
                  };
                  if (id == null) {
                    await dbHelper.insertProduct(data);
                  } else {
                    await dbHelper.updateProduct(id, data);
                  }
                  _refreshProducts();
                  Navigator.pop(context);
                },
                child: Text(id == null ? 'Tambah' : 'Simpan Perubahan'),
              ),
              SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Admin Dashboard (CRUD)'),
        actions: [IconButton(icon: Icon(Icons.logout), onPressed: () => Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => LoginScreen())))],
      ),
      body: _products.isEmpty
          ? Center(child: Text('Belum ada produk.'))
          : ListView.builder(
              itemCount: _products.length,
              itemBuilder: (context, index) {
                final prod = _products[index];
                return Card(
                  margin: EdgeInsets.all(8),
                  child: ListTile(
                    leading: Image.network(prod['image_url'], width: 50, height: 50, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(Icons.shopping_bag)),
                    title: Text(prod['name']),
                    subtitle: Text('Rp ${prod['price']} ${prod['is_featured'] == 1 ? "(Unggulan)" : ""}'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(icon: Icon(Icons.edit, color: Colors.blue), onPressed: () => _showForm(prod['id'])),
                        IconButton(icon: Icon(Icons.delete, color: Colors.red), onPressed: () async {
                          await dbHelper.deleteProduct(prod['id']);
                          _refreshProducts();
                        }),
                      ],
                    ),
                  ),
                );
              },
            ),
      floatingActionButton: FloatingActionButton(child: Icon(Icons.add), onPressed: () => _showForm(null)),
    );
  }
}