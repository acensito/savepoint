import 'package:flutter/foundation.dart'; // para debugPrint

class Console {
  /// Log normal (info)
  static void log(Object? message) {
    debugPrint('[LOG] $message');
  }

  /// Log de advertencia
  static void warn(Object? message) {
    debugPrint('[WARN] $message');
  }

  /// Log de error
  static void err(Object? message, [Object? error, StackTrace? stackTrace]) {
    debugPrint('[ERR] $message');

    if (error != null) {
      debugPrint('  Error: $error');
    }
    if (stackTrace != null) {
      debugPrint('  StackTrace: $stackTrace');
    }
  }
}
