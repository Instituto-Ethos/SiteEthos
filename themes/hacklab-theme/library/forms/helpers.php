<?php

namespace hacklabr;

function get_address_fields (): array {
    $states_options = [
		'AC' => 'Acre',
		'AL' => 'Alagoas',
		'AP' => 'Amapá',
		'AM' => 'Amazonas',
		'BA' => 'Bahia',
		'CE' => 'Ceará',
		'DF' => 'Distrito Federal',
		'ES' => 'Espírito Santo',
		'GO' => 'Goiás',
		'MA' => 'Maranhão',
		'MT' => 'Mato Grosso',
		'MS' => 'Mato Grosso do Sul',
		'MG' => 'Minas Gerais',
		'PA' => 'Pará',
		'PB' => 'Paraíba',
		'PR' => 'Paraná',
		'PE' => 'Pernambuco',
		'PI' => 'Piauí',
		'RJ' => 'Rio de Janeiro',
		'RN' => 'Rio Grande do Norte',
		'RS' => 'Rio Grande do Sul',
		'RO' => 'Rondônia',
		'RR' => 'Roraima',
		'SC' => 'Santa Catarina',
		'SP' => 'São Paulo',
		'SE' => 'Sergipe',
		'TO' => 'Tocantins',
	];

    $fields = [
        'end_logradouro' => [
            'type' => 'text',
            'class' => '-colspan-9',
            'label' => __('Address (street)', 'hacklabr'),
            'placeholder' => __('Enter the address street', 'hacklabr'),
            'required' => true,
        ],
        'end_numero' => [
            'type' => 'text',
            'class' => '-colspan-3',
            'label' => _x('Number', 'address', 'hacklabr'),
            'required' => true,
        ],
        'end_complemento' => [
            'type' => 'text',
            'class' => '-colspan-6',
            'label' => _x('Complement', 'address', 'hacklabr'),
            'placeholder' => __('Enter the address complement', 'hacklabr'),
            'required' => false,
        ],
        'end_bairro' => [
            'type' => 'text',
            'class' => '-colspan-6',
            'label' => __('Neighborhood', 'hacklabr'),
            'placeholder' => __('Enter the neighborhood', 'hacklabr'),
            'required' => true,
        ],
        'end_cidade' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('City', 'hacklabr'),
            'placeholder' => __('Enter the city', 'hacklabr'),
            'required' => true,
        ],
        'end_estado' => [
            'type' => 'select',
            'class' => '-colspan-6',
            'label' => _x('State', 'address', 'hacklabr'),
            'options' => $states_options,
            'required' => true,
            'validate' => function ($value, $context) use ($states_options) {
                if (!array_key_exists($value, $states_options)) {
                    return _x('Invalid state', 'address', 'hacklabr');
                }
                return true;
            },
        ],
        'end_cep' => [
            'type' => 'masked',
            'class' => '-colspan-6',
            'label' => __('CEP code', 'hacklabr'),
            'mask' => '00000-000',
            'placeholder' => __('Enter the CEP code', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value) || strlen($value) !== 8) {
                    return __('Invalid CEP code', 'hacklabr');
                }
                return true;
            },
        ],
    ];

    return $fields;
}

/**
 * Validate a CNPJ number.
 *
 * @param string $cnpj The CNPJ number to validate.
 * @return bool True if the CNPJ number is valid, false otherwise.
 */
function validate_cnpj($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

    if (strlen($cnpj) != 14) {
        return false;
    }

    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $calculate_dv = function($cnpj, $digits) {
        $sum = 0;
        $weight = $digits;
        for ($i = 0; $i < strlen($cnpj); $i++) {
            $sum += $cnpj[$i] * $weight--;
            if ($weight < 2) {
                $weight = 9;
            }
        }
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    };

    $dv1 = $calculate_dv(substr($cnpj, 0, 12), 5);
    $dv2 = $calculate_dv(substr($cnpj, 0, 13), 6);

    return $dv1 == $cnpj[12] && $dv2 == $cnpj[13];
}
