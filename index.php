<?php
$arquivo_json = 'palestras.json';

// Cria o arquivo se não existir
if (!file_exists($arquivo_json)) {
    file_put_contents($arquivo_json, json_encode([]));
}

$conteudo = file_get_contents($arquivo_json);
$dados = json_decode($conteudo, true);

// Garante que os dados sejam um array (mesmo se 
// o arquivo estiver vazio)
if (!is_array($dados)) {
    $dados = [];
}

// Processamento do Formulário (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? $_POST['id'] : uniqid();
    
    // Consistência Básica (Backend)
    $nova_palestra = [
        'id' => $id,
        'nome_palestra' => substr(trim($_POST['nome_palestra']), 0, 50),
        'data' => $_POST['data'],
        'horario' => preg_match('/^\d{2}:\d{2}$/', $_POST['horario']) ? $_POST['horario'] : '00:00',
        'palestrantes' => []
    ];

    // Processa até 3 palestrantes
    for ($i = 1; $i <= 4; $i++) {
        $nome = trim($_POST["nome_$i"] ?? '');
        if (!empty($nome)) {
            $nova_palestra['palestrantes'][] = [
                'nome' => substr($nome, 0, 40),
                'email' => filter_var($_POST["email_$i"], FILTER_VALIDATE_EMAIL) ? $_POST["email_$i"] : '',
                'telefone' => trim($_POST["telefone_$i"]) 
            ];
        }
    }

    // Atualiza (se o ID já existir) ou Insere nova palestra
    $atualizou = false;
    foreach ($dados as $key => $palestra) {
        if ($palestra['id'] === $id) {
            $dados[$key] = $nova_palestra; 
            $atualizou = true;
            break;
        }
    }
    
    if (!$atualizou) {
        $dados[] = $nova_palestra;
    }

    file_put_contents($arquivo_json, json_encode($dados, JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

// Exclusão (Delete)
if (isset($_GET['delete'])) {
    $id_del = $_GET['delete'];
    $dados = array_filter($dados, function($p) use ($id_del) { return $p['id'] !== $id_del; });
    file_put_contents($arquivo_json, json_encode(array_values($dados), JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

// Edição (Load data para o form)
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = $_GET['edit'];
    foreach ($dados as $p) {
        if ($p['id'] === $id_edit) $edit_data = $p;
    }
}

// ====================================================================
// NOVO CÓDIGO: ORDENAÇÃO CRONOLÓGICA (DATA E HORA)
// ====================================================================
usort($dados, function($a, $b) {
    // Junta a data e o horário para criar um tempo numérico (timestamp)
    $tempoA = strtotime($a['data'] . ' ' . $a['horario']);
    $tempoB = strtotime($b['data'] . ' ' . $b['horario']);
    
    // O operador spaceship (<=>) compara os tempos e organiza em ordem crescente
    return $tempoA <=> $tempoB;
});
// ====================================================================



?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestão de Palestras</title>
    <link rel="stylesheet" href="style1.css">        
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎤</text></svg>">
</head>
<body>

<div class="container">
    <!-- Painel de Cadastro -->
    <div class="glass-panel">
        <h2><?= $edit_data ? 'Editar Palestra' : 'Nova Palestra' ?> </h2>
        <form method="POST" action="index.php">
            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
            
            <div class="form-group">
                <label>Nome da Palestra (Max 50)</label>
                <input type="text" name="nome_palestra" maxlength="50" required 
                       oninvalid="this.setCustomValidity('Ei! Não esqueça de dar um nome para a palestra.')" 
                       oninput="this.setCustomValidity('')"
                       value="<?= $edit_data['nome_palestra'] ?? '' ?>">
            </div>
            
            
            <div class="grid-2-col">
                <div class="form-group">
                    <label>Data</label>
                    <input type="date" name="data" required 
                           oninvalid="this.setCustomValidity('Por favor, escolha a data do evento.')" 
                           oninput="this.setCustomValidity('')"
                           value="<?= $edit_data['data'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Horário (HH:MM)</label>
                    <input type="time" name="horario" required 
                           oninvalid="this.setCustomValidity('Defina o horário da apresentação.')" 
                           oninput="this.setCustomValidity('')"
                           value="<?= $edit_data['horario'] ?? '' ?>">
                </div>
            </div>

            <hr style="border: 1px solid var(--glass-border); margin: 20px 0;">

            <?php for ($i = 1; $i <= 4; $i++): 
                $p_data = $edit_data['palestrantes'][$i-1] ?? ['nome'=>'', 'email'=>'', 'telefone'=>''];
            ?>
            <div class="palestrante-box">
                <h3>Palestrante <?= $i ?> <?= $i == 1 ? '(Obrigatório)' : '' ?></h3>
                <div class="form-group">
                    <label>Nome (Max 40)</label>
                    <input type="text" name="nome_<?= $i ?>" maxlength="40" 
                           <?= $i == 1 ? 'required' : '' ?> 
                           oninvalid="this.setCustomValidity('Precisamos do nome deste palestrante!')" 
                           oninput="this.setCustomValidity('')"
                           value="<?= $p_data['nome'] ?>">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email_<?= $i ?>" 
                           <?= $i == 1 ? 'required' : '' ?> 
                           oninvalid="this.setCustomValidity('Informe um e-mail válido para contato (ex: nome@email.com).')" 
                           oninput="this.setCustomValidity('')"
                           value="<?= $p_data['email'] ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone_<?= $i ?>" placeholder="(99) 99999-9999" 
                        pattern="[\(\)\d\s-]{14,15}" 
                        maxlength="15" 
                        <?= $i == 1 ? 'required' : '' ?> 
                        oninvalid="this.setCustomValidity('Digite o telefone completo com DDD, por favor.')" 
                        oninput="this.setCustomValidity(''); mascaraTelefone(this)" 
                        value="<?= $p_data['telefone'] ?>">
                </div>
            </div>
            <?php endfor; ?>

            <button type="submit"><?= $edit_data ? 'Atualizar Dados' : 'Registrar no Sistema' ?></button>
            <?php if($edit_data): ?>
                <a href="index.php" style="display:block; text-align:center; 
                margin-top:10px; color:#bfa8a8; text-decoration:none;">Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Painel de Listagem -->
    <div class="glass-panel">
        <h2>Cadastros Ativos </h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Palestra</th>
                        <th>Data/Hora</th>
                        <th>Palestrantes</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dados)): ?>
                        <tr><td colspan="4" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dados as $palestra): ?>
                        <tr>
                            <!-- Adicionados data-labels para o CSS ler no mobile -->
                            <td data-label="Palestra"><strong><?= htmlspecialchars($palestra['nome_palestra']) ?></strong></td>
                            <td data-label="Data/Hora"><?= date('d/m/Y', strtotime($palestra['data'])) ?><br><small><?= htmlspecialchars($palestra['horario']) ?></small></td>
                            <td data-label="Palestrantes">
                                <ul style="margin:0; padding-left:15px; font-size:0.85em;">
                                    <?php foreach ($palestra['palestrantes'] as $p): ?>
                                        <li><?= htmlspecialchars($p['nome']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>                            
                            <td data-label="Ações" class="actions-cell">
                                <a href="?edit=<?= $palestra['id'] ?>">
                                    <button class="btn-action">Edit</button>
                                </a>                                                      
                                <a href="javascript:void(0)" onclick="abrirModalExclusao('<?= $palestra['id'] ?>')">
                                    <button class="btn-action btn-danger">Del</button>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Customizado de Exclusão -->
<div id="modalExclusao" class="modal-overlay">
    <div class="glass-panel modal-content">
        <h3 style="color: var(--danger); text-shadow: none;">⚠️ Confirmar Exclusão</h3>
        <p>Tem certeza que deseja apagar esta palestra permanentemente? Esta ação não pode ser desfeita.</p>
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 25px;">
            <button type="button" class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
            <button type="button" class="btn-confirmar-del" id="btnConfirmarExclusao">Sim, Excluir</button>
        </div>
    </div>
</div>

<script>
    let idParaExcluir = null;

    function abrirModalExclusao(id) {
        idParaExcluir = id;
        document.getElementById('modalExclusao').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('modalExclusao').style.display = 'none';
        idParaExcluir = null;
    }

    document.getElementById('btnConfirmarExclusao').addEventListener('click', function() {
        if (idParaExcluir) {
            window.location.href = '?delete=' + idParaExcluir;
        }
    });

    function mascaraTelefone(input) {
        let valor = input.value.replace(/\D/g, "");
        valor = valor.substring(0, 11);
        if (valor.length > 2) {
            valor = valor.replace(/^(\d{2})(\d)/g, "($1) $2"); 
        }
        if (valor.length > 9) {
            valor = valor.replace(/(\d{5})(\d{4})$/, "$1-$2"); 
        } else if (valor.length > 8) {
            valor = valor.replace(/(\d{4})(\d{4})$/, "$1-$2"); 
        }
        input.value = valor;
    }
</script>
</body>
</html>