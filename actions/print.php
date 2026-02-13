<?php
require_once '../includes/db.php';

// 1. Configurações
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');

$usuarioGerador = "Hiann Alexander Mendes de Oliveira";
$horaGeracao = date('d/m/Y \à\s H:i');

// 2. Filtros
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-01');
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-t');

// Nomes
$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$mesAtual = $meses[date('m', strtotime($data_inicio))];
$anoAtual = date('Y', strtotime($data_inicio));
$nomeArquivoPDF = 'Relatorio_' . $mesAtual . '_' . $anoAtual . '.pdf';

// 3. Dados
$sql = "SELECT t.*, c.nome as categoria_nome 
        FROM transacoes t 
        LEFT JOIN categorias c ON t.categoria_id = c.id 
        WHERE t.data_transacao BETWEEN :inicio AND :fim 
        ORDER BY t.data_transacao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais
$totalEntradas = 0; $totalSaidas = 0;
foreach($dados as $d) {
    if($d['status'] == 'pago') {
        if($d['tipo'] == 'receita') $totalEntradas += $d['valor'];
        if($d['tipo'] == 'despesa') $totalSaidas += $d['valor'];
    }
}
$saldo = $totalEntradas - $totalSaidas;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerando PDF...</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
            background: #fff;
            padding: 20px;
        }

        /* Container Principal */
        #conteudo-pdf {
            width: 100%;
            /* Aumentei a segurança das margens */
            padding: 0 10px; 
            box-sizing: border-box;
        }

        /* Cabeçalho */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center; /* Alinha verticalmente */
            border-bottom: 2px solid #1F4E78;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .title-box {
            flex: 1;
        }
        
        /* CORREÇÃO AQUI: Adicionei padding-right para não cortar */
        .meta-box {
            text-align: right;
            font-size: 10px;
            color: #555;
            flex: 1;
            padding-right: 15px; /* Margem de segurança contra corte */
        }

        .title { font-size: 22px; font-weight: bold; color: #1F4E78; margin-bottom: 5px; }
        .subtitle { font-size: 12px; color: #666; }

        /* KPIs */
        .kpi-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 15px;
        }
        .kpi-card {
            flex: 1;
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: center;
            border-radius: 6px;
            background-color: #fcfcfc;
        }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 6px; }

        .green { color: #059669; }
        .red { color: #dc2626; }
        .blue { color: #2563eb; }

        /* Tabela */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { background-color: #1F2937; color: #fff; padding: 8px; text-align: left; text-transform: uppercase; font-size: 9px; }
        td { border-bottom: 1px solid #eee; padding: 8px; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f8fafc; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        /* Badges */
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; display: inline-block; min-width: 60px; text-align: center; }
        .bg-pago { background: #dcfce7; color: #166534; }
        .bg-pendente { background: #fee2e2; color: #991b1b; }

        /* Loading */
        #loading {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.95);
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            z-index: 9999;
        }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #1F4E78; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div id="loading">
        <div class="spinner"></div>
        <div style="color: #1F4E78; font-family: sans-serif; font-weight: bold;">Gerando PDF...</div>
    </div>

    <div id="conteudo-pdf">
        
        <div class="header">
            <div class="title-box">
                <div class="title">FinancePro</div>
                <div class="subtitle">Relatório Mensal - <?= strtoupper($mesAtual) ?>/<?= $anoAtual ?></div>
            </div>
            <div class="meta-box">
                Gerado por: <b><?= $usuarioGerador ?></b><br>
                <div style="margin-top: 4px;">Emissão: <?= $horaGeracao ?></div>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Entradas (Pago)</div>
                <div class="kpi-value green">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Saídas (Pago)</div>
                <div class="kpi-value red">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Saldo Líquido</div>
                <div class="kpi-value <?= $saldo >= 0 ? 'blue' : 'red' ?>">
                    R$ <?= number_format($saldo, 2, ',', '.') ?>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="35%">DESCRIÇÃO</th>
                    <th width="15%" class="text-center">CATEGORIA</th>
                    <th width="10%" class="text-center">TIPO</th>
                    <th width="10%" class="text-center">PARC.</th>
                    <th width="15%" class="text-right">VALOR</th>
                    <th width="15%" class="text-center">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($dados as $d): 
                    $descLimpa = preg_replace('/\s*\(\d+\/\d+\)/', '', $d['descricao']);
                    $tipo = ucfirst($d['tipo']);
                    $parcela = ($d['parcelas_totais'] > 1) ? $d['parcela_atual'].'/'.$d['parcelas_totais'] : '-';
                    $corValor = ($d['tipo'] == 'receita') ? 'green' : 'red';
                    $clsStatus = ($d['status'] == 'pago') ? 'bg-pago' : 'bg-pendente';
                ?>
                <tr>
                    <td><b><?= htmlspecialchars($descLimpa) ?></b></td>
                    <td class="text-center"><?= htmlspecialchars($d['categoria_nome']) ?></td>
                    <td class="text-center"><?= $tipo ?></td>
                    <td class="text-center"><?= $parcela ?></td>
                    <td class="text-right bold <?= $corValor ?>">
                        <?= number_format($d['valor'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $clsStatus ?>"><?= strtoupper($d['status']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px; text-align: center; color: #aaa; font-size: 8px;">
            Documento gerado eletronicamente em <?= date('d/m/Y') ?>.
        </div>
    </div>

    <script>
        window.onload = function() {
            const element = document.getElementById('conteudo-pdf');
            const nomeArquivo = "<?= $nomeArquivoPDF ?>";
            
            const opt = {
                margin:       [10, 10, 10, 10], // Margem do PDF [Topo, Dir, Baixo, Esq]
                filename:     nomeArquivo,
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function(){
                document.getElementById('loading').innerHTML = '<div style="color:green; font-weight:bold;">Download Concluído!</div>';
                setTimeout(() => { window.close(); }, 1500);
            });
        };
    </script>
</body>
</html>