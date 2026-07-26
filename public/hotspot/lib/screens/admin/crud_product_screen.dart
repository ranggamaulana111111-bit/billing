import 'package:flutter/material.dart';
import '../../database/db_helper.dart';

class CrudProductScreen extends StatefulWidget {
  final Map<String, dynamic>? productData; // Jika null = Mode Tambah, jika ada isi = Mode Edit

  CrudProductScreen({this.productData});

  @override
  _CrudProductScreenState createState() => _CrudProductScreenState();
}

class _CrudProductScreenState extends State<CrudProductScreen> {
  final _formKey = GlobalKey<FormState>();
  final dbHelper = DbHelper();

  late TextEditingController _nameController;
  late TextEditingController _priceController;
  late TextEditingController _imageController;
  bool _isFeatured = false;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.productData?['name'] ?? '');
    _priceController = TextEditingController(text: widget.productData?['price']?.toString() ?? '');
    _imageController = TextEditingController(text: widget.productData?['image_url'] ?? '');
    _isFeatured = (widget.productData?['is_featured'] ?? 0) == 1;
  }

  void _saveProduct() async {
    if (_formKey.currentState!.validate()) {
      final data = {
        'name': _nameController.text,
        'price': double.parse(_priceController.text),
        'image_url': _imageController.text.isEmpty 
            ? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=500' 
            : _imageController.text,
        'is_featured': _isFeatured ? 1 : 0
      };

      if (widget.productData == null) {
        await dbHelper.insertProduct(data);
      } else {
        await dbHelper.updateProduct(widget.productData!['id'], data);
      }

      Navigator.pop(context, true); // Kirim nilai true agar dashboard tahu data berubah dan otomatis refresh
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.productData == null ? 'Tambah Produk Baru' : 'Edit Produk')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              TextFormField(
                controller: _nameController,
                decoration: InputDecoration(labelText: 'Nama Produk', border: OutlineInputBorder()),
                validator: (val) => val!.isEmpty ? 'Nama tidak boleh kosong' : null,
              ),
              SizedBox(height: 16),
              TextFormField(
                controller: _priceController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: 'Harga Produk (Rp)', border: OutlineInputBorder()),
                validator: (val) => val!.isEmpty ? 'Harga tidak boleh kosong' : null,
              ),
              SizedBox(height: 16),
              TextFormField(
                controller: _imageController,
                decoration: InputDecoration(labelText: 'URL Gambar Produk (Opsional)', border: OutlineInputBorder()),
              ),
              SizedBox(height: 16),
              CheckboxListTile(
                title: Text('Tampilkan di Produk Unggulan (Beranda User)'),
                value: _isFeatured,
                onChanged: (val) => setState(() => _isFeatured = val!),
                controlAffinity: ListTileControlAffinity.leading,
              ),
              SizedBox(height: 30),
              ElevatedButton(
                onPressed: _saveProduct,
                child: Text('Simpan Data Produk', style: TextStyle(fontSize: 16)),
                style: ElevatedButton.styleFrom(minimumSize: Size(double.infinity, 50), backgroundColor: Colors.amber[700]),
              )
            ],
          ),
        ),
      ),
    );
  }
}