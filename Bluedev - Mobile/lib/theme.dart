import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Guia visual do documento "BlueDev · Aplicativo Mobile" (página 10).
class BD {
  // Cores
  static const primary = Color(0xFF2F6FDE); // botões, FAB, links
  static const navy = Color(0xFF14224A); // títulos e texto
  static const brand = Color(0xFF1E3A8A); // logo e destaques
  static const background = Color(0xFFF4F7FB); // fundo das telas
  static const surface = Colors.white;
  static const border = Color(0xFFE3E8F1);
  static const muted = Color(0xFF8A94A8);
  static const iconBg = Color(0xFFEAF1FD);

  static const green = Color(0xFF15803D); // Resolvida
  static const greenBg = Color(0xFFDCFCE7);
  static const amber = Color(0xFFB45309); // Pendente
  static const amberBg = Color(0xFFFFF1DB);
  static const red = Color(0xFFD92D20); // Remover
  static const redBg = Color(0xFFFEE4E2);
  static const blueBg = Color(0xFFE3ECFC); // Em andamento
  static const mercadoPago = Color(0xFF009EE3);

  // Medidas
  static const margin = 16.0;
  static const cardRadius = 14.0;
  static const fieldRadius = 12.0;
  static const buttonHeight = 50.0;
  static const smallButtonHeight = 42.0;
  static const fieldHeight = 46.0;

  // Tipografia (Poppins): título de tela 19, cartão 14, texto 12–13, legenda 10,5–11,5
  static TextStyle get screenTitle => GoogleFonts.poppins(
      fontSize: 19, fontWeight: FontWeight.w700, color: navy);
  static TextStyle get cardTitle => GoogleFonts.poppins(
      fontSize: 14, fontWeight: FontWeight.w700, color: navy);
  static TextStyle get body =>
      GoogleFonts.poppins(fontSize: 13, color: navy, height: 1.45);
  static TextStyle get caption =>
      GoogleFonts.poppins(fontSize: 11, color: muted, height: 1.4);
  static TextStyle get label => GoogleFonts.poppins(
      fontSize: 11.5, fontWeight: FontWeight.w700, color: navy);

  static ThemeData theme() {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        primary: primary,
        secondary: navy,
        surface: surface,
      ),
      scaffoldBackgroundColor: background,
    );
    final fieldBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(fieldRadius),
      borderSide: const BorderSide(color: border),
    );
    return base.copyWith(
      textTheme: GoogleFonts.poppinsTextTheme(base.textTheme).apply(
        bodyColor: navy,
        displayColor: navy,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: navy,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: margin,
        titleTextStyle: screenTitle,
        shape: const Border(bottom: BorderSide(color: border)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        isDense: true,
        constraints: const BoxConstraints(minHeight: fieldHeight),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        hintStyle: GoogleFonts.poppins(fontSize: 12.5, color: muted),
        border: fieldBorder,
        enabledBorder: fieldBorder,
        focusedBorder: fieldBorder.copyWith(
            borderSide: const BorderSide(color: primary, width: 1.4)),
        errorBorder:
            fieldBorder.copyWith(borderSide: const BorderSide(color: red)),
        focusedErrorBorder: fieldBorder.copyWith(
            borderSide: const BorderSide(color: red, width: 1.4)),
        prefixIconColor: muted,
        suffixIconColor: muted,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(buttonHeight),
          textStyle:
              GoogleFonts.poppins(fontSize: 13.5, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(fieldRadius)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: navy,
          backgroundColor: surface,
          minimumSize: const Size.fromHeight(buttonHeight),
          side: const BorderSide(color: border),
          textStyle:
              GoogleFonts.poppins(fontSize: 13.5, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(fieldRadius)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          textStyle:
              GoogleFonts.poppins(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: surface,
        showDragHandle: true,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: surface,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(cardRadius)),
      ),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
