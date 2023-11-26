<?php

function calcularTaxa($valorPrazo, $valorVista, $numPrestacoes)
{
    $taxaEstimada = $valorPrazo / $valorVista;
    $precisao = 1e-4;
    $diferenca = 1;
    $cont = 0;

    while ($diferenca > $precisao) {
        $a = pow(1 + $taxaEstimada, -$numPrestacoes);
        $b = pow(1 + $taxaEstimada, -($numPrestacoes + 1));

        $f_t = $valorVista * $taxaEstimada - ($valorPrazo / $numPrestacoes) * (1 - $a);
        $f_prime_t = $valorVista - $valorPrazo * $b;

        $novaTaxa = $taxaEstimada - $f_t / $f_prime_t;
        $diferenca = abs($novaTaxa - $taxaEstimada);
        $taxaEstimada = $novaTaxa;
        $cont += 1;
    }

    return [$taxaEstimada * 100, $cont];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $n = isset($_POST["np"]) ? intval($_POST["np"]) - (isset($_POST["dp"]) ? 1 : 0) : 0;

    // Verificar se os campos obrigatórios estão presentes
    if (!isset($_POST["np"]) || !isset($_POST["pv"]) || !isset($_POST["tax"])) {
        echo json_encode(["error" => "Campos obrigatórios não fornecidos."]);
        exit;
    }

    // Validar os valores recebidos
    $np = intval($_POST["np"]);
    $pv = floatval($_POST["pv"]);
    $tax = floatval($_POST["tax"]) / 100;

    if (!is_numeric($np) || !is_numeric($tax) || !is_numeric($pv)) {
        echo json_encode(["error" => "Por favor, preencha os campos corretamente."]);
        exit;
    }

    // Definir a variável $mesesVoltar
    $mesesVoltar = isset($_POST["mesesVoltar"]) ? intval($_POST["mesesVoltar"]) : 0;

    // Inicializar as variáveis $pp e $pb
    $pp = isset($_POST["pp"]) ? floatval($_POST["pp"]) : 0.0;
    $pb = isset($_POST["pb"]) ? floatval($_POST["pb"]) : 0.0;


    $n = $np - (isset($_POST["dp"]) ? 1 : 0);
    $i = $tax != 0 ? $tax : calcularTaxa($pp, $pv, $np)[0] / 100;
    $pmt = ($i === 0) ? $pv / $n : ($pv * $i) / (1 - pow(1 + $i, -$n));

    if (!is_numeric($pp)) {
        $pp = $pmt * $n;
    }

    if (!is_numeric($pb)) {
        $pb = $pp - $pv;
    }

    $cost = $n * $pmt - $pv;
    $totalPaid = $pv + $cost;

    $resultTable = [];
    $saldoDevedor = $pv;
    $valorCorrigido = $saldoDevedor;
    $taxaAnual = (pow(1 + $i, 12) - 1) * 100;

    
    $coeficienteFinanciamento = ($i / (1 - pow(1 + $i, -$n)));

    for ($month = 1; $month <= $n; $month++) {
        $juros = $saldoDevedor * $i;
        $amortizacao = $pmt - $juros;
        $saldoDevedor -= $amortizacao;

        if ($month == $n - $mesesVoltar) {
            $valorCorrigido = $saldoDevedor;
            if ($valorCorrigido <= 0) {
                $valorCorrigido = 0;
                $saldoDevedor = 0;
            }
        }

        $resultTable[] = [
            "month" => $month,
            "pmt" => round($pmt, 2),
            "juros" => round($juros, 2),
            "amortizacao" => round($amortizacao, 2),
            "saldoDevedor" => round($saldoDevedor, 2),
        ];
    }

    // Adicionar o cabeçalho Content-Type para indicar que a resposta é JSON
    header('Content-Type: application/json');

    // Retornar valores arredondados no formato JSON
    echo json_encode([
        "pmt" => round($pmt, 2),
        "pp" => round($pp, 2),
        "pb" => round($pb, 2),
        "cost" => round($cost, 2),
        "totalPaid" => round($totalPaid, 2),
        "resultTable" => $resultTable,
        "valorFinanciado" => round($pv, 2),
        "valorVoltar" => round($pb, 2),
        "valorCorrigido" => round($valorCorrigido, 2),
        "np" => $np,
        "tax" => $tax,
        "taxaAnual" => round($taxaAnual, 2),
        "mesesVoltar" => $mesesVoltar, 
        "taxReal" => round(calcularTaxa($pp, $pv, $np)[0], 4),
        "count" => round(calcularTaxa($pp, $pv, $np)[1]),
        "coef" => round($coeficienteFinanciamento, 6)
    ]);

    exit;
}
?>