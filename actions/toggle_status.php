<?php
// 1. Inicia o "absorvedor" de sujeira do PHP
ob_start(); 
require_once '../includes/db.php';
require_once '../includes/functions.php';
// Limpa qualquer espaço em branco ou erro que tenha vazado dos arquivos acima
ob_end_clean(); 

// 2. Avisa o navegador que a resposta oficial é um JSON
header('Content-Type: application/json');

try {
    // Recebe os dados do JavaScript
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Nenhum ID recebido.']);
        exit;
    }

    $id = (int)$data['id'];
    $dataInicio = $data['data_inicio'] ?? date('Y-m-01');
    $dataFim = $data['data_fim'] ?? date('Y-m-t');

    // 3. Descobre qual é o status atual
    $stmt = $pdo->prepare("SELECT status FROM transacoes WHERE id = ?");
    $stmt->execute([$id]);
    $transacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transacao) {
        echo json_encode(['success' => false, 'message' => 'Transação não encontrada.']);
        exit;
    }

    // 4. Inverte o status
    $novoStatus = ($transacao['status'] === 'pendente') ? 'pago' : 'pendente';

    // 5. Salva no banco
    $update = $pdo->prepare("UPDATE transacoes SET status = ? WHERE id = ?");
    $update->execute([$novoStatus, $id]);

    // 6. Recalcula os totais em tempo real
    $sqlTotais = "SELECT 
        SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END) as entradas,
        SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END) as saidas,
        SUM(CASE WHEN tipo = 'despesa' AND status = 'pendente' THEN valor ELSE 0 END) as pendente
        FROM transacoes 
        WHERE data_transacao BETWEEN ? AND ?";
    
    $stmtTotais = $pdo->prepare($sqlTotais);
    $stmtTotais->execute([$dataInicio, $dataFim]);
    $totais = $stmtTotais->fetch(PDO::FETCH_ASSOC);

    $entradas = (float)($totais['entradas'] ?? 0);
    $saidas = (float)($totais['saidas'] ?? 0);
    $pendente = (float)($totais['pendente'] ?? 0);
    $saldo = $entradas - $saidas;

    // 7. Devolve o sucesso para o JavaScript
    echo json_encode([
        'success' => true,
        'novo_status' => $novoStatus,
        'totais' => [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $saldo,
            'pendente' => $pendente
        ]
    ]);

} catch (Throwable $e) {
    // Se der erro de banco de dados, devolve o erro formatado em JSON
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}