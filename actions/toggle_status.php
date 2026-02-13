<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

// Recebe o JSON do Javascript
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

// Recebe as datas do filtro atual (se vierem do JS), senão usa o mês atual real
$data_inicio = $data['data_inicio'] ?? date('Y-m-01');
$data_fim = $data['data_fim'] ?? date('Y-m-t');

if ($id) {
    try {
        // 1. Busca o status atual para inverter
        $stmt = $pdo->prepare("SELECT status FROM transacoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $transacao = $stmt->fetch();

        if ($transacao) {
            $novoStatus = ($transacao['status'] === 'pago') ? 'pendente' : 'pago';
            
            // 2. Atualiza no Banco
            $update = $pdo->prepare("UPDATE transacoes SET status = :status WHERE id = :id");
            $update->execute([':status' => $novoStatus, ':id' => $id]);
            
            // 3. Recalcula os Totais (RESPEITANDO AS DATAS DO DASHBOARD)
            // Calculamos Entradas, Saídas e Pendentes apenas dentro do range de datas que o usuário está vendo
            $sqlTotais = "SELECT 
                SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END) as entradas, 
                SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END) as saidas, 
                SUM(CASE WHEN tipo = 'despesa' AND status = 'pendente' THEN valor ELSE 0 END) as contas_a_pagar,
                (SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END) - 
                 SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END)) as saldo
                FROM transacoes 
                WHERE data_transacao BETWEEN :inicio AND :fim";

            $stmtTotais = $pdo->prepare($sqlTotais);
            $stmtTotais->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
            $totais = $stmtTotais->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true, 
                'novo_status' => $novoStatus,
                'totais' => [
                    'entradas' => (float)$totais['entradas'],
                    'saidas' => (float)$totais['saidas'],
                    'saldo' => (float)$totais['saldo'],
                    'pendente' => (float)$totais['contas_a_pagar']
                ]
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'ID inválido']);
exit;