<?php

namespace ethos\crm;

enum AccountStatus: int {
    case Active = 0;
    case Inactive = 1;

    public static function isActive (int $statecode): bool {
        $value = self::tryFrom($statecode);
        return $value === self::Active;
    }
}

enum BrazilianUF: int {
    case AC = 7;
    case AL = 15;
    case AM = 1;
    case AP = 3;
    case BA = 16;
    case CE = 10;
    case DF = 20;
    case ES = 23;
    case GO = 19;
    case MA = 8;
    case MG = 24;
    case MS = 18;
    case MT = 17;
    case PA = 4;
    case PB = 13;
    case PE = 12;
    case PI = 9;
    case PR = 25;
    case RJ = 22;
    case RN = 11;
    case RO = 6;
    case RR = 2;
    case RS = 26;
    case SC = 27;
    case SE = 14;
    case SP = 21;
    case TO = 5;

    public static function fromCode (string|null $uf): int|null {
        if (empty($uf)) {
            return null;
        }

        return match ($uf) {
            'AC' => self::AC->value,
            'AL' => self::AL->value,
            'AM' => self::AM->value,
            'AP' => self::AP->value,
            'BA' => self::BA->value,
            'CE' => self::CE->value,
            'DF' => self::DF->value,
            'ES' => self::ES->value,
            'GO' => self::GO->value,
            'MA' => self::MA->value,
            'MG' => self::MG->value,
            'MS' => self::MS->value,
            'MT' => self::MT->value,
            'PA' => self::PA->value,
            'PB' => self::PB->value,
            'PE' => self::PE->value,
            'PI' => self::PI->value,
            'PR' => self::PR->value,
            'RJ' => self::RJ->value,
            'RN' => self::RN->value,
            'RO' => self::RO->value,
            'RR' => self::RR->value,
            'RS' => self::RS->value,
            'SC' => self::SC->value,
            'SE' => self::SE->value,
            'SP' => self::SP->value,
            'TO' => self::TO->value,
            default => null
        };
    }
}

enum ContactStatus: int {
    case Active = 0;
    case Inactive = 1;

    public static function isActive (int $statecode): bool {
        $value = self::tryFrom($statecode);
        return $value === self::Active;
    }
}

enum CompanySize: int {
    case Micro = 969830000;
    case Pequena = 969830001;
    case Media = 969830002;
    case MediaGrande = 969830003;
    case Grande1 = 969830004;
    case Grande2 = 969830005;
    case Grande3 = 969830006;

    public static function fromSlug (string $slug): int {
        return match ($slug) {
            'micro' => self::Micro->value,
            'small' => self::Pequena->value,
            'medium' => self::Media->value,
            'large' => self::Grande1->value,
        };
    }

    public static function toSlug (int|null $pl_porte): string {
        if (empty($pl_porte)) {
            return 'micro';
        }

        $size = self::from($pl_porte);
        return match ($size) {
            self::Micro => 'micro',
            self::Pequena => 'small',
            self::Media, self::MediaGrande => 'medium',
            self::Grande1, self::Grande2, self::Grande3 => 'large',
        };
    }
}

enum Plan: int {
    case Conexao = 969830000;
    case Essencial = 969830001;
    case Vivencia = 969830002;
    case Institucional = 969830003;

    public static function fromLevel (int $level_id) {
        return match ($level_id) {
            8, 12, 16, 20 => self::Conexao,
            9, 13, 17, 21 => self::Essencial,
            10, 14, 18 => self::Vivencia,
            11, 15, 19 => self::Institucional,
        };
    }

    public function toLevel (string $company_size, bool $for_manager = true): int {
        if ($company_size === 'large') {
            return match ($this) {
                self::Conexao => 16,
                self::Essencial => 17,
                self::Vivencia => 18,
                self::Institucional => 19,
            };
        } elseif ($company_size === 'medium') {
            return match ($this) {
                self::Conexao => 12,
                self::Essencial => 13,
                self::Vivencia => 14,
                self::Institucional => 15,
            };
        } else {
            return match ($this) {
                self::Conexao => $for_manager ? 8 : 20,
                self::Essencial => $for_manager ? 9 : 21,
                self::Vivencia => 10,
                self::Institucional => 11,
            };
        }
    }

    public function toSlug (): string {
        return match ($this) {
            self::Conexao => 'conexao',
            self::Essencial => 'essencial',
            self::Institucional => 'institucional',
            self::Vivencia => 'vivencia',
            default => 'conexao',
        };
    }
}
