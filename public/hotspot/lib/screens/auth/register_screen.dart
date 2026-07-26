import 'package:flutter/material.dart';
import 'package:sqflite/sqflite.dart';
import '../../database/db_helper.dart';

class RegisterScreen extends StatefulWidget {
  @override
  _RegisterScreenState createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final dbHelper = DbHelper();

  void _register() async {
    String username = _usernameController.text.trim();
    String password = _passwordController.text.trim();

    if (username.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Form tidak boleh kosong!')));
      return;
    }

    try {
      final db = await dbHelper.database;
      await db.insert('users', {
        'username': username,
        'password': password,
        'role': 'user' // Default pendaftar baru adalah user umum
      });

      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Registrasi Berhasil! Silakan Login.')));
      Navigator.pop(context); // Kembali ke halaman Login
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Username sudah terpakai!')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Daftar Akun Baru')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TextField(controller: _usernameController, decoration: InputDecoration(labelText: 'Buat Username', border: OutlineInputBorder())),
            SizedBox(height: 16),
            TextField(controller: _passwordController, obscureText: true, decoration: InputDecoration(labelText: 'Buat Password', border: OutlineInputBorder())),
            SizedBox(height: 24),
            ElevatedButton(
              onPressed: _register,
              child: Text('Daftar Sekarang'),
              style: ElevatedButton.styleFrom(minimumSize: Size(double.infinity, 50)),
            ),
          ],
        ),
      ),
    );
  }
}