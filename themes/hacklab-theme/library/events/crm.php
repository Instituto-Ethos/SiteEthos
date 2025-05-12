<?php

namespace hacklabr;

function register_for_event( array $params ) {
    $contact_id = $params['contact_id'];
    $project_id = $params['project_id'];

    $attibutes = [
        // 'createdbyyominame' => '',
        // 'createdonbehalfbyyominame' => '',
        'fut_lk_contato' => create_crm_reference( 'contato', $contact_id ),
        // 'fut_lk_empresa' => create_crm_reference( 'account', '' ),
        // 'fut_lk_empresa_associada' => create_crm_reference( 'account', '' ),
        // 'fut_lk_fatura_pf' => create_crm_reference( 'contato', $contact_id ),
        // 'fut_lk_fatura_pj' => create_crm_reference( 'account', $contact_id ),
        // 'fut_parceria_participante_id' => create_crm_reference( 'fut_parceria', '' ),
        // 'fut_pl_cortesia' => '',
        'fut_lk_projeto' => create_crm_reference( 'fut_projeto', $project_id ),
        // 'fut_participanteid' => '',
        // 'modifiedbyyominame' => '',
        // 'modifiedonbehalfbyyominame' => '',
        // 'organizationid' => create_crm_reference( 'organization', '' ),
        // 'organizationidname' => '',
        // 'statecode' => '',
        // 'transactioncurrencyid' => create_crm_reference( 'transactioncurrency', '' ),
    ];

    try {
        $entity_id = create_crm_entity( 'fut_participante', $attibutes );

        return [
            'status'    => 'success',
            'message'   => 'Entidade criada com sucesso no CRM.',
            'entity_id' => $entity_id,
        ];
    } catch ( \Exception $e ) {
        return [
            'status'  => 'error',
            'message' => "Erro ao inscrever-se no event {$project_id}: " . $e->getMessage()
        ];
    }
}
