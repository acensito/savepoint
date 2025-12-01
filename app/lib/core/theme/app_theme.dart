import 'package:flutter/material.dart';


class AppTheme {
  static final ThemeData lightTheme = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF6200EE), // Morado SavePoint
      brightness: Brightness.light,
    ),
    // textTheme: GoogleFonts.poppinsTextTheme(),
    appBarTheme: const AppBarTheme(
      centerTitle: true,
      elevation: 0,
    ),
  );
  
  // (Opcional) En el futuro pondremos aquí el darkTheme
}