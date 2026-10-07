# Testes das etapas 1, 2 e 3

Estes arquivos são auxiliares. A aplicação não depende deles e o Apache bloqueia seu acesso HTTP.

Use uma instância separada de MySQL/MariaDB e uma cópia do projeto atendida pelo Apache. Nunca importe o SQL inicial sobre o banco atual.

Defina `MY_CASH_DB_HOST=127.0.0.1`, `MY_CASH_DB_PORT` com a porta da instância isolada e `MY_CASH_DB_NAME` com um nome novo terminado em `_test`. Configure essas mesmas variáveis no Apache de teste. Defina `MY_CASH_TEST_URL` com o endereço dessa cópia, por exemplo `http://127.0.0.1:18880/Projeto_Leticia`.

Execute `C:\xampp\php\php.exe tests\seguranca.php`. O teste cria somente o banco novo de teste e recusa bancos existentes. As portas 3306, 80 e 443 são recusadas. Não execute com o banco real.

Para a etapa 3, execute `C:\xampp\php\php.exe tests\funcionalidades.php` nas mesmas condições de isolamento. O teste cobre categorias, descrição e reativação de setores, atualização automática de atrasos, informações consolidadas do dashboard, últimas movimentações e gráfico do setor.

O relatório informa PASS/FAIL e total. A verificação visual de menu, modais e telas deve ser feita também no navegador. Os testes desta etapa não comprovam as correções financeiras previstas para a etapa 2.

## Migração das senhas existentes

1. Faça backup do banco e valide a migração em uma cópia.
2. Pare temporariamente as operações no sistema.
3. Confira os dados de conexão. `php scripts/migrar_senhas.php` apenas simula e não muda senhas.
4. Com autorização para o banco correto, execute `php scripts/migrar_senhas.php --aplicar`.
5. Confirme o login com as senhas anteriores. IDs, e-mails e dados financeiros são preservados; hashes reconhecidos não são recalculados.

O login corrigido não aceita texto puro. Portanto, no banco existente, a migração precisa ocorrer antes de usar esse login. Não foi aplicada automaticamente. Não reimporte `banco_my_cash.sql` para migrar senhas.

## Testes financeiros da etapa 2

Execute C:\xampp\php\php.exe tests\financeiro.php com as mesmas proteções de isolamento descritas acima. Use um banco novo terminado em _test e configure o Apache de teste para esse mesmo nome. Nunca use o banco atual.

O teste cobre valores, parcelas e datas de fim de mês, receitas/despesas pendentes e efetivadas, distribuição, recolhimento, transferência, edição, estorno, saldo insuficiente e desativação de setores. Também força falhas SQL para comprovar o rollback e envia requisições simultâneas de sessões separadas.

O teste reproduz temporariamente o esquema anterior na cópia e verifica que permitir destino NULL preserva os registros. NULL significa Saldo Geral: distribuição tem origem NULL e destino setor; recolhimento tem origem setor e destino NULL; transferência entre setores tem ambos os IDs.

Se scripts/migrar_transferencias.php estiver disponível, o teste verifica simulação, aplicação e repetição desse script apenas no banco de teste. Caso contrário, informa que essa parte não é testável e faz a alteração mínima diretamente na cópia para testar as operações. Isso não autoriza nem realiza migração no banco atual.

Movimentações antigas sem receita/despesa/transferência vinculada não são corrigidas por suposição. O estorno as bloqueia e pede conferência. Recolhimentos antigos podem não guardar o ID do setor de origem; essa informação não pode ser reconstruída apenas pelo valor.

O número envio do formulário impede repetir um cadastro ou transferência concluída. O token CSRF continua protegendo todos os formulários. Quando um envio expira ou já foi usado, a página deve ser atualizada.

Os testes são auxiliares, não são carregados pelas telas e não introduzem dependências para executar o aplicativo. O relatório automatizado não substitui conferência visual no navegador.
