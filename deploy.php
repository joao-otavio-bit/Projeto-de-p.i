<?php
$mensagem = "";
$status = "";
$totalArquivos = 0;

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pastaOrigem = trim($_POST['origem']);
    $pastaDestino = trim($_POST['destino']);
    $nomeArquivoZip = trim($_POST['nome_zip']);

    // Garante que o arquivo tenha a extensão .zip
    if (strtolower(pathinfo($nomeArquivoZip, PATHINFO_EXTENSION)) !== 'zip') {
        $nomeArquivoZip .= '.zip';
    }

    $caminhoCompletoZip = $pastaDestino . '/' . $nomeArquivoZip;

    // Validações básicas
    if (empty($pastaOrigem) || empty($pastaDestino) || empty($nomeArquivoZip)) {
        $mensagem = "Por favor, preencha todos os campos.";
        $status = "erro";
    } elseif (!is_dir($pastaOrigem)) {
        $mensagem = "A pasta de <strong>origem</strong> não foi encontrada ou não existe.";
        $status = "erro";
    } else {
        // 1. Cria a pasta destino se não existir
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0777, true);
        }

        // 2. Prepara o arquivo ZIP
        $zip = new ZipArchive();
        $zipCriado = $zip->open($caminhoCompletoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($zipCriado === true) {
            // 3. Lê os arquivos da pasta de origem
            $arquivos = scandir($pastaOrigem);
            $nomeScriptAtual = basename(__FILE__);

            foreach ($arquivos as $arquivo) {
                // Ignora ".", ".." e o próprio script se ele estiver na mesma pasta
                if ($arquivo === '.' || $arquivo === '..' || $arquivo === $nomeScriptAtual) {
                    continue;
                }
                
                $caminhoOrigem = $pastaOrigem . '/' . $arquivo;
                
                // Evita loops infinitos se a pasta de destino estiver dentro da origem
                if (realpath($caminhoOrigem) === realpath($pastaDestino)) {
                    continue; 
                }
                
                // Verifica se é um arquivo
                if (is_file($caminhoOrigem)) {
                    $caminhoCopia = $pastaDestino . '/' . $arquivo;
                    
                    // A) Copia o original
                    copy($caminhoOrigem, $caminhoCopia);
                    
                    // B) Cria a versão .txt
                    $info = pathinfo($arquivo);
                    $nome = $info['filename'];
                    $extensao = isset($info['extension']) ? $info['extension'] : '';
                    
                    $nomeTxt = $extensao ? "{$nome}_{$extensao}.txt" : "{$nome}.txt";
                    $caminhoTxt = $pastaDestino . '/' . $nomeTxt;
                    
                    copy($caminhoOrigem, $caminhoTxt);
                    
                    // C) Adiciona ao ZIP
                    $zip->addFile($caminhoOrigem, $arquivo);
                    
                    $totalArquivos++;
                }
            }
            
            $zip->close();
            
            if ($totalArquivos > 0) {
                $mensagem = "Sucesso! <strong>{$totalArquivos}</strong> arquivos foram processados e salvos em <em>{$pastaDestino}</em>.";
                $status = "sucesso";
            } else {
                $mensagem = "A pasta de origem estava vazia (nenhum arquivo para copiar).";
                $status = "aviso";
            }
        } else {
            $mensagem = "Falha ao tentar gerar o arquivo ZIP.";
            $status = "erro";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automação de Arquivos</title>
    <style>
        :root {
            --bg-color: #0d1117;
            --glass-bg: rgba(22, 27, 34, 0.6);
            --glass-border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(1, 4, 9, 0.5);
            --text-main: #c9d1d9;
            --accent-blue: #2f81f7;
            --accent-success: #2ea043;
            --accent-error: #f85149;
            --accent-warning: #d29922;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(47, 129, 247, 0.10), transparent 30%),
                radial-gradient(circle at 85% 30%, rgba(46, 160, 67, 0.10), transparent 30%);
            padding: 20px;
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            font-weight: 600;
            color: #ffffff;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #8b949e;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(47, 129, 247, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 10px;
            background-color: #238636;
            color: #ffffff;
            border: 1px solid rgba(240, 246, 252, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        button:hover {
            background-color: #2ea043;
        }

        /* Telas de Resposta */
        .status-box {
            text-align: center;
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .sucesso .icon { color: var(--accent-success); text-shadow: 0 0 20px rgba(46, 160, 67, 0.5); }
        .erro .icon { color: var(--accent-error); text-shadow: 0 0 20px rgba(248, 81, 73, 0.5); }
        .aviso .icon { color: var(--accent-warning); text-shadow: 0 0 20px rgba(210, 153, 34, 0.5); }

        .status-box p {
            font-size: 1.05rem;
            line-height: 1.6;
            color: #8b949e;
        }

        .status-box p strong, .status-box p em {
            color: #ffffff;
            font-style: normal;
        }

        .btn-voltar {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 24px;
            background-color: transparent;
            color: var(--text-main);
            text-decoration: none;
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-voltar:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <div class="glass-panel">
        <?php if ($status): ?>
            <!-- Tela de Resultado -->
            <div class="status-box <?= $status ?>">
                <div class="icon">
                    <?= $status === 'sucesso' ? '✓' : ($status === 'erro' ? '✕' : '!') ?>
                </div>
                <h2><?= $status === 'sucesso' ? 'Processo Concluído' : ($status === 'erro' ? 'Atenção' : 'Aviso') ?></h2>
                <p><?= $mensagem ?></p>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-voltar">Nova Transferência</a>
            </div>
        <?php else: ?>
            <!-- Formulário Inicial -->
            <h2>Painel de Automação</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Pasta de Origem</label>
                    <input type="text" name="origem" placeholder="Ex: C:\xampp\htdocs\meus_arquivos" required 
                           value="<?= __DIR__ ?>">
                </div>
                
                <div class="form-group">
                    <label>Pasta de Destino</label>
                    <input type="text" name="destino" placeholder="Ex: C:\xampp\htdocs\backup" required
                           value="<?= __DIR__ . '\enviar' ?>">
                </div>

                <div class="form-group">
                    <label>Nome do Arquivo ZIP</label>
                    <input type="text" name="nome_zip" placeholder="Ex: pacote_alunos.zip" required
                           value="backup_originais.zip">
                </div>

                <button type="submit">Processar Arquivos</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>