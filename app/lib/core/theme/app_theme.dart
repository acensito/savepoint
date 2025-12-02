import 'package:flutter/foundation.dart'; // NECESARIO para kIsWeb
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // Colores (Los mantenemos igual)
  static const Color primary = Color(0xFF6200EE);
  static const Color secondary = Color(0xFF03DAC6);
  static const Color background = Color(0xFFF5F5F5);
  static const Color surface = Colors.white;

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      
      // 1. DENSIDAD VISUAL
      // Si es Web, usamos 'compact' (menos aire). Si es móvil, 'standard'.
      visualDensity: kIsWeb ? VisualDensity.compact : VisualDensity.standard,

      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        secondary: secondary,
        surface: surface,
      ),

      // 2. FUENTES
      // En Web, Poppins se ve genial, pero asegúrate de que el tamaño no sea gigante
      textTheme: GoogleFonts.poppinsTextTheme(),

      // 3. QUITANDO EL EFECTO ANDROID (Ripple)
      // En web queda más limpio sin la explosión de tinta al hacer clic
      splashFactory: kIsWeb ? NoSplash.splashFactory : InkRipple.splashFactory,
      
      // 4. BARRAS DE DESPLAZAMIENTO (Scrollbars)
      // En Web queremos que se vean siempre
      scrollbarTheme: ScrollbarThemeData(
        thumbVisibility: WidgetStateProperty.all(kIsWeb ? true : false),
        thickness: WidgetStateProperty.all(8),
        radius: const Radius.circular(8),
      ),

      // 5. TARJETAS (Cards)
      // En web quedan mejor con menos elevación (más planas)
      cardTheme: CardThemeData(
        elevation: kIsWeb ? 0 : 2, // En web plano con borde, en móvil con sombra
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: kIsWeb ? BorderSide(color: Colors.grey.shade300) : BorderSide.none,
        ),
        color: surface,
      ),
      
      // 6. APPBAR
      // En web la queremos blanca o transparente, no morada
      appBarTheme: const AppBarTheme(
        centerTitle: false, // En web el título suele ir a la izquierda
        backgroundColor: surface,
        elevation: 0,
        iconTheme: IconThemeData(color: Colors.black87),
        titleTextStyle: TextStyle(color: Colors.black87, fontSize: 20, fontWeight: FontWeight.bold),
      ),
    );
  }
}