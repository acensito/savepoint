import 'dart:io';
import 'package:flutter/foundation.dart'; // Para saber si es Web

class AppConstants {
  // Lógica automática:
  // - Si es Web o iOS: usa localhost (127.0.0.1)
  // - Si es Android Emulador: usa 192.168.0.2
  static String get baseUrl {
    if (kIsWeb) return "http://127.0.0.1:8090";
    if (Platform.isAndroid) return "http://192.168.5.11:8090";
    return "http://127.0.0.1:8090";
  }
}