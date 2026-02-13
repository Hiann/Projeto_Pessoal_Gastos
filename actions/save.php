<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- 1. RECEBIMENTO DOS DADOS ---
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $descricao = trim($_POST['descricao']); // Remove espaços extras
    
    // Tratamento de Moeda (Blindado)
    $valorRaw = $_POST['valor'];
    // Se tiver vírgula, assumimos formato BR (remove ponto de milhar, troca vírgula por ponto)
    if (strpos($valorRaw, ',') !== false) {
        $valorRaw = str_replace('.', '', $valorRaw);
        $valorRaw = str_replace(',', '.', $valorRaw);
    }
    $valorTotal = floatval($valorRaw);
    
    $dataBase = $_POST['data'];
    $tipo = $_POST['tipo'];
    $categoria_id = filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_NUMBER_INT);
    $status = isset($_POST['status_pago']) ? 'pago' : 'pendente';

    // Recorrência
    $modoRepeticao = $_POST['repeticao'] ?? 'unica'; 
    $qtdParcelas = (int)($_POST['parcelas'] ?? 1);
    if($qtdParcelas < 1) $qtdParcelas = 1;

    // --- 2. VALIDAÇÃO ---
    if ($descricao && $valorTotal > 0 && $dataBase && $categoria_id) {
        
        try {
            $pdo->beginTransaction();

            // === MODO EDIÇÃO (Não mexe no parcelamento, edita apenas o registro atual) ===
            if (!empty($id)) {
                $sql = "UPDATE transacoes SET descricao=:d, valor=:v, data_transacao=:dt, tipo=:t, categoria_id=:c, status=:s WHERE id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':d' => $descricao, ':v' => $valorTotal, ':dt' => $dataBase, 
                    ':t' => $tipo, ':c' => $categoria_id, ':s' => $status, ':id' => $id
                ]);
            } 
            
            // === MODO CRIAÇÃO (Gera 1 ou várias transações) ===
            else {
                $grupoId = uniqid('trans_'); // ID para agrupar as parcelas
                
                // PREPARA O VALOR DA PARCELA
                // Se for "parcelada" E tiver mais de 1 parcela, divide. Senão, usa o valor cheio.
                if ($modoRepeticao === 'parcelada' && $qtdParcelas > 1) {
                    // Ex: 100 / 3 = 33.33
                    $valorParcelaBase = floor(($valorTotal / $qtdParcelas) * 100) / 100;
                    // Calcula a diferença de centavos (Ex: 0.01)
                    $diferencaCentavos = round($valorTotal - ($valorParcelaBase * $qtdParcelas), 2);
                } else {
                    $valorParcelaBase = $valorTotal;
                    $diferencaCentavos = 0;
                }

                // LOOP DE CRIAÇÃO
                for ($i = 0; $i < $qtdParcelas; $i++) {
                    
                    // 1. Data (Incrementa meses)
                    $dataObj = new DateTime($dataBase);
                    $dataObj->modify("+$i month");
                    $dataFinal = $dataObj->format('Y-m-d');

                    // 2. Descrição e Valor
                    $descFinal = $descricao;
                    $valorFinal = $valorParcelaBase;

                    if ($modoRepeticao === 'parcelada' && $qtdParcelas > 1) {
                        // Adiciona (1/5) na descrição
                        $num = $i + 1;
                        $descFinal .= " ($num/$qtdParcelas)";
                        
                        // Soma os centavos que sobraram na PRIMEIRA parcela
                        if ($i === 0) {
                            $valorFinal += $diferencaCentavos;
                        }
                    } 
                    // Se for Fixa (Assinatura), mantém valor total e nome limpo (ou adiciona 'Mensal' se preferir)
                    
                    // 3. Status (Só a primeira segue o status do form, o resto é pendente)
                    $statusFinal = ($i === 0) ? $status : 'pendente';

                    // 4. Salva no Banco
                    $sql = "INSERT INTO transacoes (descricao, valor, data_transacao, tipo, categoria_id, status, parcela_atual, parcelas_totais, grupo_id) 
                            VALUES (:d, :v, :dt, :t, :c, :s, :pa, :pt, :g)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':d' => $descFinal,
                        ':v' => $valorFinal, // Aqui vai o valor dividido!
                        ':dt' => $dataFinal,
                        ':t' => $tipo,
                        ':c' => $categoria_id,
                        ':s' => $statusFinal,
                        ':pa' => $i + 1,
                        ':pt' => $qtdParcelas,
                        ':g' => $grupoId
                    ]);
                }
            }

            $pdo->commit();
            header('Location: ../index.php?status=success');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: ../index.php?status=error');
            exit;
        }
    } else {
        header('Location: ../index.php?status=error&msg=invalid_data');
        exit;
    }
} else {
    header('Location: ../index.php');
    exit;
}