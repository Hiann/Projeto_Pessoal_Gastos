<?php
require_once '../includes/db.php';

// 1. Configurações
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');

$usuarioGerador = "Hiann Alexander Mendes de Oliveira";
$horaGeracao = date('H:i'); // Apenas a hora

// 2. Filtros
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-01');
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-t');

// Nome do Arquivo
$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$mesAtual = $meses[date('m', strtotime($data_inicio))];
$anoAtual = date('Y', strtotime($data_inicio));
$arquivo = 'Relatorio_' . $mesAtual . '_' . $anoAtual . '.xls';

// 3. Headers
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"{$arquivo}\"");

// 4. Dados
$sql = "SELECT t.*, c.nome as categoria_nome 
        FROM transacoes t 
        LEFT JOIN categorias c ON t.categoria_id = c.id 
        WHERE t.data_transacao BETWEEN :inicio AND :fim 
        ORDER BY t.data_transacao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- CORREÇÃO: CÁLCULO DOS TOTAIS (Faltava no seu código) ---
$totalEntradas = 0;
$totalSaidas = 0;
foreach($dados as $d) {
    if($d['status'] == 'pago') {
        if($d['tipo'] == 'receita') $totalEntradas += $d['valor'];
        if($d['tipo'] == 'despesa') $totalSaidas += $d['valor'];
    }
}
$saldo = $totalEntradas - $totalSaidas;
// -----------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<style>

body {
    font-family: Calibri, Arial, sans-serif;
    font-size: 11pt;
}

/* ================= HEADER ================= */

.titulo {
    background-color: #1F4E78;
    color: #FFFFFF;
    font-size: 20pt;
    font-weight: bold;
    height: 60px;
    padding-left: 15px;
    vertical-align: middle;
}

.meta {
    background-color: #F2F2F2;
    font-size: 9pt;
    padding: 10px;
    text-align: right;
}

/* ================= KPIs ================= */

.kpi-label {
    background-color: #E7EEF5;
    font-weight: bold;
    text-align: center;
    border: 1px solid #D0D0D0;
    height: 25px;
}

.kpi-value {
    font-size: 16pt;
    font-weight: bold;
    text-align: center;
    border: 1px solid #D0D0D0;
    height: 45px;
}

.entrada { color: #006100; }
.saida { color: #9C0006; }
.saldo-positivo { color: #1F4E78; }
.saldo-negativo { color: #FF0000; }

/* ================= TABELA ================= */

th {
    background-color: #1F1F1F;
    color: #FFFFFF;
    font-weight: bold;
    height: 32px;
    border: 1px solid #A6A6A6;
}

td {
    border: 1px solid #D9D9D9;
    padding: 6px;
    height: 26px;
}

.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }

.money {
    mso-number-format:"R$ \#\,\#\#0\.00";
    font-weight: bold;
}

.status-pago {
    background-color: #C6EFCE;
    color: #006100;
    font-weight: bold;
}

.status-pendente {
    background-color: #FFC7CE;
    color: #9C0006;
    font-weight: bold;
}

.linha-alternada {
    background-color: #FAFAFA;
}

</style>
</head>

<body>

<table width="100%" cellspacing="0" cellpadding="0">

<tr>
    <td colspan="4" class="titulo">
        RELATÓRIO FINANCEIRO
    </td>
    <td colspan="2" class="meta">
        <b>Gerado por:</b> <?= $usuarioGerador ?><br>
        <b>Data:</b> <?= date('d/m/Y') ?> às <?= $horaGeracao ?>
    </td>
</tr>

<tr><td colspan="6" height="20"></td></tr>

<tr>
    <td colspan="2" class="kpi-label">TOTAL ENTRADAS</td>
    <td class="kpi-label">TOTAL SAÍDAS</td>
    <td colspan="2" class="kpi-label">SALDO ATUAL</td>
    <td colspan="1"></td>
</tr>

<tr>
    <td colspan="2" class="kpi-value entrada money"><?= $totalEntradas ?></td>
    <td class="kpi-value saida money"><?= $totalSaidas ?></td>
    <td colspan="2" class="kpi-value money <?= $saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo' ?>">
        <?= $saldo ?>
    </td>
    <td colspan="1"></td>
</tr>

<tr><td colspan="6" height="25"></td></tr>

<tr>
    <th>DESCRIÇÃO</th>
    <th>CATEGORIA</th>
    <th>TIPO</th>
    <th>PARCELA</th>
    <th>VALOR</th>
    <th>STATUS</th>
</tr>

<?php 
$linha = 0;
foreach($dados as $d):

$linha++;
$classeLinha = ($linha % 2 == 0) ? 'linha-alternada' : '';

$tipo = ucfirst($d['tipo']);
$parcela = ($d['parcelas_totais'] > 1) ? 
    $d['parcela_atual'].'/'.$d['parcelas_totais'] : '-';

// Limpa a descrição: Remove coisas como (1/5) ou (02/12)
$descricaoLimpa = preg_replace('/\s*\(\d+\/\d+\)/', '', $d['descricao']);

$corValor = ($d['tipo'] == 'receita') ? '#006100' : '#9C0006';
$classStatus = ($d['status'] == 'pago') ? 'status-pago' : 'status-pendente';
?>

<tr class="<?= $classeLinha ?>">
    <td class="text-left"><?= htmlspecialchars($descricaoLimpa) ?></td>
    <td class="text-center"><?= htmlspecialchars($d['categoria_nome']) ?></td>
    <td class="text-center"><?= $tipo ?></td>
    <td class="text-center" style='mso-number-format:"\@" '><?= $parcela ?></td>
    <td class="text-right money" style="color: <?= $corValor ?>;">
        <?= $d['valor'] ?>
    </td>
    <td class="text-center <?= $classStatus ?>">
        <?= strtoupper($d['status']) ?>
    </td>
</tr>

<?php endforeach; ?>

</table>

</body>
</html>