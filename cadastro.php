<?php
session_start();

// Configuração dos arquivos
$arq_palestras = 'palestras.json';
$arq_participantes = 'participantes.json';
$arq_inscricoes = 'inscricoes.json';

// Inicializa arquivos se não existirem
foreach ([$arq_participantes, $arq_inscricoes] as $arquivo) {
    if (!file_exists($arquivo)) {
        file_put_contents($arquivo, json_encode([]));
    }
}

// Carrega os dados
$palestras = file_exists($arq_palestras) ? json_decode(file_get_contents($arq_palestras), true) : [];
$participantes = json_decode(file_get_contents($arq_participantes), true) ?? [];
$inscricoes = json_decode(file_get_contents($arq_inscricoes), true) ?? [];

$mensagem = '';
$tipo_msg = '';

// ====================================================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. CADASTRO DE NOVO OUVINTE
    if ($acao === 'cadastrar') {
        $nome = trim($_POST['nome']);
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $telefone = trim($_POST['telefone']);
        $senha = $_POST['senha'];
        $senha_confirma = $_POST['senha_confirma'];

        if (!$email) {
            $mensagem = "E-mail inválido!";
            $tipo_msg = "error";
        } elseif ($senha !== $senha_confirma) {
            $mensagem = "As senhas não coincidem!";
            $tipo_msg = "error";
        } else {
            // Verifica se e-mail já existe
            $existe = false;
            foreach ($participantes as $p) {
                if ($p['email'] === $email) { $existe = true; break; }
            }

            if ($existe) {
                $mensagem = "Este e-mail já está cadastrado.";
                $tipo_msg = "error";
            } else {
                // Cria o usuário com senha criptografada (hash seguro)
                $novo_id = uniqid('ouv_');
                $participantes[] = [
                    'id' => $novo_id,
                    'nome' => $nome,
                    'email' => $email,
                    'telefone' => $telefone,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT)
                ];
                file_put_contents($arq_participantes, json_encode($participantes, JSON_PRETTY_PRINT));
                
                // Já faz o login automático
                $_SESSION['ouvinte_id'] = $novo_id;
                header("Location: cadastro.php");
                exit;
            }
        }
    }

    // 2. LOGIN
    if ($acao === 'login') {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $logado = false;

        foreach ($participantes as $p) {
            if ($p['email'] === $email && password_verify($senha, $p['senha'])) {
                $_SESSION['ouvinte_id'] = $p['id'];
                $logado = true;
                header("Location: cadastro.php");
                exit;
            }
        }

        if (!$logado) {
            $mensagem = "E-mail ou senha incorretos.";
            $tipo_msg = "error";
        }
    }

    // 3. ATUALIZAR DADOS
    if ($acao === 'atualizar_perfil' && isset($_SESSION['ouvinte_id'])) {
        $id_logado = $_SESSION['ouvinte_id'];
        $senha_atual = $_POST['senha_atual'];
        $novo_nome = trim($_POST['nome']);
        $novo_telefone = trim($_POST['telefone']);

        $atualizou = false;
        foreach ($participantes as $key => $p) {
            if ($p['id'] === $id_logado) {
                // Exige a senha atual correta para permitir alteração
                if (password_verify($senha_atual, $p['senha'])) {
                    $participantes[$key]['nome'] = $novo_nome;
                    $participantes[$key]['telefone'] = $novo_telefone;
                    
                    // Se digitou senha nova, atualiza
                    if (!empty($_POST['senha_nova']) && $_POST['senha_nova'] === $_POST['senha_nova_confirma']) {
                        $participantes[$key]['senha'] = password_hash($_POST['senha_nova'], PASSWORD_DEFAULT);
                    }
                    
                    file_put_contents($arq_participantes, json_encode($participantes, JSON_PRETTY_PRINT));
                    $mensagem = "Dados atualizados com sucesso!";
                    $tipo_msg = "success";
                    $atualizou = true;
                } else {
                    $mensagem = "Senha atual incorreta. Nenhuma alteração feita.";
                    $tipo_msg = "error";
                }
                break;
            }
        }
    }
}

// ====================================================================
// PROCESSAMENTO DE INSCRIÇÃO / DESISTÊNCIA (GET)
// ====================================================================
if (isset($_GET['toggle_palestra']) && isset($_SESSION['ouvinte_id'])) {
    $id_palestra = $_GET['toggle_palestra'];
    $id_logado = $_SESSION['ouvinte_id'];

    // Inicializa o array do usuário se não existir
    if (!isset($inscricoes[$id_logado])) {
        $inscricoes[$id_logado] = [];
    }

    $pos = array_search($id_palestra, $inscricoes[$id_logado]);
    if ($pos !== false) {
        // Se já tá inscrito, cancela (remove do array)
        unset($inscricoes[$id_logado][$pos]);
        // Reindexa o array
        $inscricoes[$id_logado] = array_values($inscricoes[$id_logado]); 
    } else {
        // Se não tá inscrito, adiciona
        $inscricoes[$id_logado][] = $id_palestra;
    }

    file_put_contents($arq_inscricoes, json_encode($inscricoes, JSON_PRETTY_PRINT));
    header("Location: cadastro.php");
    exit;
}

// LOGOUT
if (isset($_GET['sair'])) {
    session_destroy();
    header("Location: cadastro.php");
    exit;
}

// Pega dados do usuário logado para exibir
$usuario_logado = null;
if (isset($_SESSION['ouvinte_id'])) {
    foreach ($participantes as $p) {
        if ($p['id'] === $_SESSION['ouvinte_id']) {
            $usuario_logado = $p;
            break;
        }
    }
    // Ordena as palestras cronologicamente
    usort($palestras, function($a, $b) {
        return strtotime($a['data'] . ' ' . $a['horario']) <=> strtotime($b['data'] . ' ' . $b['horario']);
    });
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Eventos</title>
    <link rel="stylesheet" href="cadastro1.css">
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 
    viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🪪</text></svg>">
</head>
<body>

<div class="container">
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_msg ?>"><?= $mensagem ?></div>
    <?php endif; ?>

    <?php if (!$usuario_logado): ?>
        <!-- ================= TELA DE LOGIN / CADASTRO ================= -->
        <div style="text-align: center; margin-bottom: 40px;">
            <h1>Eventos <span>Liberty</span></h1>
            <p style="color: var(--text-muted);">Acesse ou cadastre-se para participar das palestras.</p>
        </div>

        <div class="auth-grid">
            <!-- Painel de Login -->
            <div class="glass-panel">
                <h2>Acessar <span>Conta</span></h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="login">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required
                               oninvalid="this.setCustomValidity('Informe seu e-mail cadastrado.')" 
                               oninput="this.setCustomValidity('')">
                    </div>
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" name="senha" required>
                    </div>
                    <button type="submit">Entrar no Sistema</button>
                </form>
            </div>

            <!-- Painel de Cadastro -->
            <div class="glass-panel">
                <h2>Novo <span>Cadastro</span></h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="cadastrar">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required
                               oninvalid="this.setCustomValidity('Informe um e-mail válido para contato (ex: nome@email.com).')" 
                               oninput="this.setCustomValidity('')">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" placeholder="(99) 99999-9999" 
                               pattern="[\(\)\d\s-]{14,15}" 
                               maxlength="15" 
                               required 
                               oninvalid="this.setCustomValidity('Digite o telefone completo com DDD, por favor.')" 
                               oninput="this.setCustomValidity(''); mascaraTelefone(this)">
                    </div>
                    <div class="form-group">
                        <label>Criar Senha</label>
                        <input type="password" name="senha" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmar Senha</label>
                        <input type="password" name="senha_confirma" required>
                    </div>
                    <button type="submit" class="btn-secondary">Registrar</button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- ================= PAINEL DO OUVINTE LOGADO ================= -->
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Olá, <span><?= htmlspecialchars(explode(' ', $usuario_logado['nome'])[0]) ?></span></h1>
            <div style="display: flex; gap: 10px;">
                <button onclick="document.getElementById('modalPerfil').style.display='block'" 
                class="btn-secondary" style="width:auto; padding: 10px 15px;">Configurações</button>
                <a href="?sair=1"><button style="width:auto; padding: 10px 15px; background: transparent; 
                border: 1px solid var(--text-muted);">Sair</button></a>
            </div>
        </div>

        <div class="glass-panel">
            <h2>Palestras <span>Disponíveis</span></h2>
            <p style="color: var(--text-muted); margin-bottom: 25px;">Selecione os eventos que deseja participar.</p>
            
            <div class="palestras-grid">
                <?php if (empty($palestras)): ?>
                    <p>Nenhuma palestra programada no momento.</p>
                <?php else: ?>
                    <?php 
                    $minhas_inscricoes = $inscricoes[$usuario_logado['id']] ?? [];
                    foreach ($palestras as $palestra): 
                        $esta_inscrito = in_array($palestra['id'], $minhas_inscricoes);
                    ?>
                        <div class="palestra-card" style="<?= $esta_inscrito ? 'border-color: var(--honda-red); 
                        box-shadow: inset 0 0 15px rgba(208,0,0,0.15);' : '' ?>">
                            <div>
                                <div class="palestra-header">
                                    <h3 class="palestra-title"><?= htmlspecialchars($palestra['nome_palestra']) ?></h3>
                                    <div class="palestra-meta">
                                        <span>📅 <?= date('d/m/Y', strtotime($palestra['data'])) ?></span>
                                        <span>⏱️ <?= htmlspecialchars($palestra['horario']) ?></span>
                                    </div>
                                </div>
                                <div style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 20px;">
                                    <strong>Palestrantes:</strong><br>
                                    <?php foreach ($palestra['palestrantes'] as $p): ?>
                                        - <?= htmlspecialchars($p['nome']) ?><br>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <a href="?toggle_palestra=<?= $palestra['id'] ?>" style="text-decoration: none;">
                                <?php if ($esta_inscrito): ?>
                                    <button class="btn-desistir">Cancelar Inscrição</button>
                                <?php else: ?>
                                    <button class="btn-inscrever">Quero Participar</button>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL DE ATUALIZAÇÃO DE PERFIL -->
        <div id="modalPerfil" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
        background:rgba(0,0,0,0.8); z-index:1000; padding:20px; box-sizing:border-box;">
            <div class="glass-panel" style="max-width:500px; margin: 50px auto; position:relative;">
                <button onclick="document.getElementById('modalPerfil').style.display='none'" 
                style="position:absolute; top:15px; right:15px; width:auto; padding:5px 10px; 
                background:transparent; border:none; color:var(--text-muted);">X</button>
                <h2>Meus <span>Dados</span></h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="atualizar_perfil">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario_logado['nome']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($usuario_logado['telefone']) ?>" 
                               placeholder="(99) 99999-9999" 
                               pattern="[\(\)\d\s-]{14,15}" 
                               maxlength="15" 
                               required 
                               oninvalid="this.setCustomValidity('Digite o telefone completo com DDD, por favor.')" 
                               oninput="this.setCustomValidity(''); mascaraTelefone(this)">
                    </div>
                    <hr style="border: 1px solid var(--glass-border); margin: 20px 0;">
                    <div class="form-group">
                        <label>Nova Senha (deixe em branco se não quiser mudar)</label>
                        <input type="password" name="senha_nova">
                    </div>
                    <div class="form-group">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="senha_nova_confirma">
                    </div>
                    <hr style="border: 1px solid var(--glass-border); margin: 20px 0;">
                    <div class="form-group">
                        <label style="color:var(--honda-red);">Para salvar alterações, digite sua senha ATUAL:</label>
                        <input type="password" name="senha_atual" required>
                    </div>
                    <button type="submit">Salvar Alterações</button>
                </form>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
    // Função para adicionar a máscara de telefone automaticamente enquanto o usuário digita
    function mascaraTelefone(input) {
        let valor = input.value.replace(/\D/g, ""); // Remove tudo o que não é dígito
        valor = valor.substring(0, 11); // Limita o tamanho a 11 números
        
        if (valor.length > 2) {
            valor = valor.replace(/^(\d{2})(\d)/g, "($1) $2"); // Coloca parênteses no DDD
        }
        if (valor.length > 9) {
            valor = valor.replace(/(\d{5})(\d{4})$/, "$1-$2"); // Formato celular: (99) 99999-9999
        } else if (valor.length > 8) {
            valor = valor.replace(/(\d{4})(\d{4})$/, "$1-$2"); // Formato fixo: (99) 9999-9999
        }
        
        input.value = valor;
    }
</script>

</body>
</html>