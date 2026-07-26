import 'package:flutter/material.dart';
import '../../database/db_helper.dart';
import '../admin/admin_dashboard.dart';
import '../user/user_dashboard.dart';

class LoginScreen extends StatefulWidget {
  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final dbHelper = DbHelper();

  void _login() async {
    String username = _usernameController.text.trim();
    String password = _passwordController.text.trim();

    if (username.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Semua kolom wajib diisi!')));
      return;
    }

    var user = await dbHelper.login(username, password);

    if (user != null) {
      if (user['role'] == 'admin') {
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => AdminDashboard()));
      } else {
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => UserDashboard(userId: user['id'])));
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Username atau Password salah!')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Digital Supermarket Login'), centerTitle: true),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TextField(controller: _usernameController, decoration: InputDecoration(labelText: 'Username', border: OutlineInputBorder())),
            SizedBox(height: 16),
            TextField(controller: _passwordController, obscureText: true, decoration: InputDecoration(labelText: 'Password', border: OutlineInputBorder())),
            SizedBox(height: 24),
            ElevatedButton(
              onPressed: _login,
              child: Text('Masuk', style: TextStyle(fontSize: 16)),
              style: ElevatedButton.styleFrom(minimumSize: Size(double.infinity, 50)),
            )
          ],
        ),
      ),
    );
  }
}