<?php
// pages/partial/sidebar.php
$baseUrl = '/Projeto_Leticia/pages';
$actionUrl = '/Projeto_Leticia/actions';

require_once __DIR__ . '/../../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../autentificacao/login.php');
    exit;
}

try {
    $stmtSidebar = $pdo->prepare("SELECT id_setor, nome, descricao, ativo FROM setores ORDER BY ativo DESC, nome");
    $stmtSidebar->execute();
    $catalogoSetores = $stmtSidebar->fetchAll(PDO::FETCH_ASSOC);
    $setoresAtivos = array_values(array_filter($catalogoSetores, fn($setor) => (bool) $setor['ativo']));
    $setoresInativos = array_values(array_filter($catalogoSetores, fn($setor) => !(bool) $setor['ativo']));
} catch (Throwable $e) {
    $setoresAtivos = [];
    $setoresInativos = [];
}
$current_url = $_SERVER['REQUEST_URI'];
?>
<aside id="sidebar">
  <ul class="menu-list">
    <li>
      <a href="<?= $baseUrl ?>/dashboard.php" class="menu-item">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Resumo Financeiro</span>
      </a>
    </li>
    <li>
      <a href="<?= $baseUrl ?>/historico.php" class="menu-item">
          <i class="fa-regular fa-file-lines"></i>
        <span>Resumo administrativo</span>
      </a>
    </li>
    <li>
      <a href="<?= $baseUrl ?>/categorias.php" class="menu-item">
        <i class="fa-solid fa-tags"></i>
        <span>Categorias</span>
      </a>
    </li>

    <li>
      <details class="menu-dropdown">
        <summary class="menu-item">
          <i class="fa-regular fa-building"></i>
          <span>Setores</span>
          <i class="fa-solid fa-chevron-down chevron"></i>
        </summary>
        
        <ul class="submenu-list">
          <?php if (!empty($setoresAtivos)): ?>
            <?php foreach ($setoresAtivos as $itemSetor): ?>
              <li class="submenu-item-container">
                <a href="<?= $baseUrl ?>/setores/detalhes.php?id=<?= $itemSetor['id_setor'] ?>" class="submenu-item-link">
                  <?= htmlspecialchars($itemSetor['nome']) ?>
                </a>
                
                <div class="sidebar-dropdown-container">
                    <button class="btn-dots" onclick="toggleSidebarMenu(event, <?= $itemSetor['id_setor'] ?>)">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="sidebar-action-menu" id="menu-setor-<?= $itemSetor['id_setor'] ?>">
                        <button type="button" onclick="abrirModalSidebar('modalRenomearSetor', <?= $itemSetor['id_setor'] ?>, <?= htmlspecialchars(json_encode($itemSetor['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($itemSetor['descricao'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        
                        <!-- NOVO BOTÃO: EXCLUIR EM VEZ DE MESCLAR -->
                        <button type="button" onclick="abrirModalSidebar('modalExcluirSetor', <?= $itemSetor['id_setor'] ?>, <?= htmlspecialchars(json_encode($itemSetor['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-ban"></i> Desativar
                        </button>
                    </div>
                </div>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
             <li>
                <span class="submenu-item-link" style="color: rgba(255,255,255,0.5);">Nenhum setor</span>
             </li>
          <?php endif; ?>
          
          <li>
            <button class="btn-add-setor" onclick="document.getElementById('modalNovoSetor').style.display='flex'">
              Adicionar setor +
            </button>
          </li>
          <?php if (!empty($setoresInativos)): ?>
            <li class="submenu-title">Setores inativos</li>
            <?php foreach ($setoresInativos as $itemSetor): ?>
              <li class="submenu-item-container setor-inativo">
                <span class="submenu-item-link"><?= htmlspecialchars($itemSetor['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                <form action="<?= $actionUrl ?>/ativar_setor.php" method="POST">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="id_setor" value="<?= (int) $itemSetor['id_setor'] ?>">
                  <button type="submit" class="btn-ativar-setor" title="Reativar setor"><i class="fa-solid fa-rotate-left"></i></button>
                </form>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </details>
    </li>
  </ul>

  <ul class="menu-list" style="margin-top: auto;">
    <li>
      <a href="<?= $baseUrl ?>/perfil/perfil.php" class="menu-item">        
    <i class="fa-solid fa-user-gear"></i>
        <span>Meu Perfil</span>
      </a>
    </li>
    <li>
      <form action="<?= $baseUrl ?>/autentificacao/logout.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="menu-item">
          <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span>
        </button>
      </form>
    </li>
  </ul>
</aside>

<div id="modalNovoSetor" class="modal-overlay-sidebar">
    <div class="modal-content-sidebar">
        <div class="modal-header-sidebar">
            <h3>Novo Setor</h3>
            <button type="button" class="close-modal-sidebar" onclick="document.getElementById('modalNovoSetor').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="<?= $actionUrl ?>/setor_novo.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($current_url) ?>">
            <div class="form-group-sidebar">
                <label>Nome do Novo Setor</label>
                <input type="text" name="nome_setor" class="form-control-sidebar" maxlength="100" placeholder="Ex: Recursos Humanos" required>
            </div>
            <div class="form-group-sidebar">
                <label>Descricao</label>
                <textarea name="descricao_setor" class="form-control-sidebar" maxlength="255" rows="3" placeholder="Explique a finalidade do setor"></textarea>
            </div>
            <button type="submit" class="btn-sidebar-primary">Criar Setor</button>
        </form>
    </div>
</div>

<div id="modalRenomearSetor" class="modal-overlay-sidebar">
    <div class="modal-content-sidebar">
        <div class="modal-header-sidebar">
            <h3>Editar Setor</h3>
            <button type="button" class="close-modal-sidebar" onclick="document.getElementById('modalRenomearSetor').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="<?= $actionUrl ?>/setor_renomear.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($current_url) ?>">
            <input type="hidden" name="id_setor" id="renomear_id_setor">
            <div class="form-group-sidebar">
                <label>Novo Nome</label>
                <input type="text" name="novo_nome" id="renomear_nome_setor" class="form-control-sidebar" maxlength="100" required>
            </div>
            <div class="form-group-sidebar">
                <label>Descricao</label>
                <textarea name="nova_descricao" id="renomear_descricao_setor" class="form-control-sidebar" maxlength="255" rows="3"></textarea>
            </div>
            <button type="submit" class="btn-sidebar-primary">Salvar Alteração</button>
        </form>
    </div>
</div>

<!-- A tabela preserva o setor; esta acao apenas o desativa. -->
<div id="modalExcluirSetor" class="modal-overlay-sidebar">
    <div class="modal-content-sidebar">
        <div class="modal-header-sidebar">
            <h3>Desativar Setor</h3>
            <button type="button" class="close-modal-sidebar" onclick="document.getElementById('modalExcluirSetor').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="<?= $actionUrl ?>/excluir_setor.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_setor" id="excluir_id_setor">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($current_url) ?>">
            
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px; line-height: 1.5;">
                Tem certeza que deseja desativar o setor <strong id="excluir_nome_setor" style="color: #ef4444;"></strong>? <br><br>
                <b>Atenção:</b> Por questões de segurança financeira, o sistema só permite excluir setores que possuam <b style="color: #1e293b;">saldo igual a R$ 0,00</b>.
            </p>
            
            <button type="submit" class="btn-sidebar-danger">
                <i class="fa-solid fa-trash"></i> Confirmar Exclusão
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const botao = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('sidebar');
        if (botao && menu) {
            botao.addEventListener('click', function () {
                menu.classList.toggle('active');
                botao.setAttribute('aria-expanded', menu.classList.contains('active'));
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    menu.classList.remove('active');
                    botao.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
    document.addEventListener("click", function(event) {
        if (!event.target.closest('.sidebar-dropdown-container')) {
            document.querySelectorAll('.sidebar-action-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });

    function toggleSidebarMenu(event, id) {
        event.preventDefault();
        event.stopPropagation();
        
        document.querySelectorAll('.sidebar-action-menu').forEach(menu => {
            if(menu.id !== 'menu-setor-' + id) menu.style.display = 'none';
        });

        const menu = document.getElementById('menu-setor-' + id);
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    function abrirModalSidebar(modalId, id_setor, nome_setor, descricao_setor = '') {
        document.getElementById(modalId).style.display = 'flex';
        
        document.getElementById('menu-setor-' + id_setor).style.display = 'none';
        
        if (modalId === 'modalRenomearSetor') {
            document.getElementById('renomear_id_setor').value = id_setor;
            document.getElementById('renomear_nome_setor').value = nome_setor;
            document.getElementById('renomear_descricao_setor').value = descricao_setor;
        } 
        else if (modalId === 'modalExcluirSetor') {
            document.getElementById('excluir_id_setor').value = id_setor;
            document.getElementById('excluir_nome_setor').innerText = nome_setor;
        }
    }
</script>
