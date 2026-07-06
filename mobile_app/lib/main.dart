import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'providers/cart_provider.dart';
import 'screens/home_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => CartProvider()),
      ],
      child: const SwaadEPunjabApp(),
    ),
  );
}

class SwaadEPunjabApp extends StatelessWidget {
  const SwaadEPunjabApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Swaad E Punjab',
      debugShowCheckedModeBanner: false,
      themeMode: ThemeMode.light,
      theme: ThemeData(
        brightness: Brightness.light,
        scaffoldBackgroundColor: const Color(0xFFFFFDF5),
        primaryColor: const Color(0xFFE6A817),
        colorScheme: const ColorScheme.light(
          primary: Color(0xFFE6A817),
          secondary: Color(0xFF2C1A00),
          background: Color(0xFFFFFDF5),
          surface: Color(0xFFFFFFFF),
          error: Color(0xFFDC2626),
        ),
        textTheme: GoogleFonts.outfitTextTheme(
          ThemeData.light().textTheme.apply(
            bodyColor: const Color(0xFF1A0F00),
            displayColor: const Color(0xFF1A0F00),
          ),
        ),
      ),
      home: const HomeScreen(),
    );
  }
}
