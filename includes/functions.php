<?php

/**
 * Formata um valor decimal para o padrão Monetário Brasileiro (R$)
 * Ex: 1500.50 -> R$ 1.500,50
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata uma data do banco (YYYY-MM-DD) para o padrão BR (DD/MM/YYYY)
 * Ex: 2026-02-10 -> 10/02/2026
 */
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

/**
 * Define a classe CSS baseada no status
 * Útil para não encher o HTML de if/else
 */
function getStatusClass($status) {
    return $status === 'pago' ? 'pago' : 'pendente';
}

/**
 * Retorna o texto amigável do status
 */
function getStatusLabel($status) {
    return $status === 'pago' ? 'Pago' : 'Pendente';
}

/**
 * Resume um texto longo (para não quebrar o layout em mobile)
 */
function encurtarTexto($texto, $limite = 30) {
    if (strlen($texto) > $limite) {
        return substr($texto, 0, $limite) . '...';
    }
    return $texto;
}
?>