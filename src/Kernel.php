<?php

namespace App;

use App\Doctrine\EncryptedStringType;
use App\Security\Encryptor;
use Doctrine\DBAL\Types\Type;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;


class Kernel extends BaseKernel {

  use MicroKernelTrait;

  // registra il tipo di dato personalizzato per le stringhe cifrate
  public function boot(): void {
    parent::boot();
    if (!Type::hasType(EncryptedStringType::NAME)) {
      Type::addType(EncryptedStringType::NAME, EncryptedStringType::class);
    }
    $encryptor = $this->getContainer()->get(Encryptor::class);
    EncryptedStringType::setEncryptor($encryptor);
  }

}
