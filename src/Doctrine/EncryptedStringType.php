<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Doctrine;


use App\Security\Encryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;


/**
 * EncryptedStringType - Tipo Doctrine per gestire le stringhe cifrate
 *
 * @author Antonello Dessì
 */
class EncryptedStringType extends Type {

  //==================== COSTANTI ====================

  // Nome del tipo di dato personalizzato
  public const NAME = 'encrypted_string';


  //==================== ATTRIBUTI ====================

  // Variabile statica per il servizio di cifratura
  private static ?Encryptor $encryptor = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Imposta il servizio di cifratura
   *
   * @param Encryptor $encryptor Istanza del servizio di cifratura
   */
  public static function setEncryptor(Encryptor $encryptor): void {
    self::$encryptor = $encryptor;
  }

  /**
   * Restituisce la dichiarazione SQL per il tipo di dato
   *
   * @param array $column Colonna del database
   * @param AbstractPlatform $platform Piattaforma del database
   *
   * @return string Dichiarazione SQL per il tipo di dato
   */
  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string {
    // utilizza la dichiarazione TEXT per il tipo di dato cifrato
    return $platform->getClobTypeDeclarationSQL($column);
  }

  /**
   * Converte il valore dal database al tipo PHP
   *
   * @param mixed $value Valore dal database
   * @param AbstractPlatform $platform Piattaforma del database
   *
   * @return string|null Valore convertito
   */
  public function convertToPHPValue($value, AbstractPlatform $platform): ?string {
    if ($value === null) {
      return $value;
    }
    return self::$encryptor->decrypt($value);
  }

  /**
   * Converte il valore dal tipo PHP al database
   *
   * @param mixed $value Valore da convertire
   * @param AbstractPlatform $platform Piattaforma del database
   *
   * @return string|null Valore convertito
   */
  public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string {
    if ($value === null) {
      return $value;
    }
    return self::$encryptor->encrypt($value);
  }

  /**
   * Restituisce il nome del tipo di dato personalizzato
   *
   * @return string Nome del tipo di dato
   */
  public function getName(): string {
    return self::NAME;
  }

}
