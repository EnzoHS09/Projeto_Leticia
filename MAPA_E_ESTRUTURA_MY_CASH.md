# MAPA E ESTRUTURA DO PROJETO — MY CASH (VERSÃO FINAL IMPLEMENTADA)

> Documento de planeamento e registo arquitetural para os desenvolvedores do My Cash.
> Stack: **HTML + CSS + PHP puro + MySQL**.
> Objetivo: manter o projeto **simples, seguro, organizado em camadas (MVC-like)**, sem framework externo, mas com experiência de utilizador (UX) premium baseada em modais.

---

# 1. Decisões de arquitetura

## 1.1. Separação principal

* `pages/` → páginas visuais principais acessadas pelo administrador (Dashboard, Histórico, Detalhes, Erro).
* `pages/partial/` → componentes visuais partilhados (ex: Sidebar).
* `actions/` → arquivos PHP que recebem `POST`, processam regras de negócio e redirecionam (Controller).
* `crud/` → funções isoladas de interação com o MySQL, protegidas com `prepare()` (Model).
* `config/` → configuração geral e conexão com o banco de dados via PDO.
* `assets/` → CSS puro, organizado por módulos.
* `database/` → script SQL consolidado (v4).

Fluxo padrão:

MODAL NA PÁGINA VISUAL
      ↓ POST
ARQUIVO ACTION (Processamento)
      ↓ Chama função
ARQUIVO CRUD (MySQL)
      ↓ 
REDIRECT (Sucesso → Volta à página / Falha → erro.php)



Exemplo prático de fluxo:



Dashboard (Modal Novo Lançamento)
      ↓ POST
actions/salvar_lancamento_completo.php
      ↓ Chama função criarContaReceber()
crud/crud_contas_receber.php
      ↓ INSERT INTO contas_receber (MySQL)
Dashboard (Recarregado com os novos dados)



## 1.2. Operações financeiras efetivadas

Receitas, despesas e transferências já efetivadas não possuem edição financeira direta. Se uma operação for registada incorretamente:


operação original
      ↓
   ESTORNO (via botão no Histórico)
      ↓
nova operação correta criada via Modal



Assim, o registo de auditoria, o histórico e os saldos continuam 100% coerentes.

## 1.3. Receita x conta a receber


DINHEIRO JÁ ENTROU
        ↓
      RECEITA (Lançamento Efetivado)
        ↓
   Saldo Geral

DINHEIRO AINDA NÃO ENTROU
        ↓
  CONTA A RECEBER (Lançamento Pendente)
        ↓
 PENDENTE / ATRASADO
        ↓
Confirmar Recebimento (Modal de Quitação)
        ↓
      RECEITA
        ↓
   Saldo Geral



## 1.4. Despesa x compromisso


DINHEIRO JÁ SAIU
        ↓
      DESPESA (Lançamento Efetivado)
        ↓
Saldo do setor diminui




DINHEIRO AINDA NÃO SAIU
        ↓
     COMPROMISSO (Lançamento Pendente)
        ↓
 PENDENTE / ATRASADO
        ↓
Confirmar Pagamento (Modal de Quitação)
        ↓
      DESPESA
        ↓
Saldo do setor diminui



**Importante:** receber uma conta a receber gera **RECEITA**; pagar um compromisso gera **DESPESA**. Não é necessário um tipo separado `PAGAMENTO` no histórico.

## 1.5. Saldo Geral

O Saldo Geral não tem CRUD próprio. Ele é mostrado no Dashboard, alterado estritamente pelas operações financeiras do sistema (Receitas e Transferências) e nunca editado manualmente.

## 1.6. Exclusão Lógica (Soft Delete) e Erros Globais

* **Soft Delete:** Setores, Categorias e Administradores possuem uma coluna `ativo`. Nunca usamos `DELETE` na base de dados para estes itens. Os registos são apenas ocultados (`UPDATE ativo = FALSE`) para proteger a integridade dos relatórios financeiros antigos.
* **Tratamento de Erros:** Exceções de regras de negócio capturadas nas `actions/` enviam a mensagem de erro via URL (`urlencode`) para a página visual global `erro.php`, garantindo que o utilizador nunca vê ecrãs de erro do PHP.

## 1.7. Administrador Inicial (Sem página de 1º acesso)

Para manter o sistema limpo e evitar a necessidade de uma página dedicada exclusivamente à criação do primeiro administrador, o sistema é inicializado com um utilizador padrão na base de dados.

* **Nome:** admin
* **E-mail:** admin@gmail.com
* **Senha:** 123 (a ser convertida em hash seguro na inserção manual ou através do próprio mecanismo de login na primeira verificação).
A partir deste administrador, novos acessos podem ser criados internamente.

---

# 2. Mapa completo das páginas

A adoção de modais eliminou a necessidade de dezenas de ficheiros visuais (`nova.php`, `editar.php`), centralizando o controlo analítico e de inserção.


/
├── sem sessão
│       ↓
│   pages/autentificacao/login.php
│       ↓
│   actions/salvar_login.php (lógica fundida no próprio form/action)
│       ↓
│   pages/dashboard.php
│
└── sessão ativa
        ↓
    pages/dashboard.php (Visão global, saldos, gráficos, transferências)
        │
        ├── MODAIS (Dashboard e Detalhes de Setor)
        │   ├── Novo Lançamento (Receita/Despesa, Pendente/Efetivada, Parcelamento)
        │   ├── Quitação de Pendência (Baixar Conta/Pagar Compromisso)
        │   └── Editar Lançamento Pendente
        │
        ├── GESTÃO DE SETORES (Sidebar)
        │   ├── Modal: Novo Setor
        │   ├── Modal: Renomear Setor
        │   └── Modal: Excluir Setor
        │
        ├── VISÃO DE SETOR
        │   └── pages/setores/detalhes.php (Filtros, Pendentes, Efetivadas locais)
        │
        ├── HISTÓRICO DE AUDITORIA
        │   └── pages/historico.php (Listagem, Filtros, Botão de Estorno)
        │
        ├── PERFIL E ADMINISTRAÇÃO
        │   ├── pages/perfil/perfil.php (Edição de dados e senha)
        │   └── pages/perfil/cadastro.php (Criação de novos admins)
        │
        ├── PROTEÇÃO GLOBAL
        │   └── pages/erro.php (Captura de regras de negócio violadas)
        │
        └── SAIR
            └── pages/autentificacao/logout.php



---

# 3. Navegação

## Menu lateral (`partial/sidebar.php`)

A Sidebar é dinâmica e carrega a lista de setores ativos diretamente da base de dados, permitindo gestão imediata.


Resumo Financeiro (Dashboard)
Resumo Administrativo (Histórico)

Setores (Dropdown Dinâmico)
├── [Lista de Setores Ativos] -> Abre detalhes.php?id=X
├── [⋮] Opções Inline: Renomear | Excluir
└── Adicionar setor +

Meu Perfil
Sair



## Após o Login

Após login válido, a rota de aterragem é o `Dashboard`.

Diferente da estrutura original planeada, as páginas não exigem navegação de "vai e vem" entre ecrãs com botões `[Salvar]` e `[Cancelar]`. A experiência foi otimizada:

* **Lançamentos e Transferências:** Ocorrem sempre via **Modais** (janelas sobrepostas) ou **Painéis de Abas (Tabs)** embutidos diretamente no `Dashboard` e na página de `Detalhes do Setor`.
* **Quitação de Pendências:** Uma conta a receber ou compromisso apresenta o botão `[Baixar]` ou `[Pagar]` diretamente na listagem. Ao clicar, um Modal de Quitação é aberto para confirmar o valor final e a data efetiva, gerando imediatamente a receita ou despesa associada.
* **Listagens Dinâmicas:** As tabelas possuem filtros dedicados no topo (pesquisa, datas, tipos).
* **Estornos:** O botão de estorno aparece exclusivamente na listagem do `Histórico`. O estorno reverte a operação gerada (devolvendo saldos) e, no caso de pendências, regressa o estado da conta a receber ou compromisso para `PENDENTE`.

---

# 4. Estrutura completa de pastas


my-cash/
│
├── config/
│   └── conexao.php
│
├── crud/
│   ├── crud_administradores.php
│   ├── crud_categorias.php
│   ├── crud_compromissos.php
│   ├── crud_contas_receber.php
│   └── crud_setores.php
│
├── actions/
│   ├── atualizar_perfil.php
│   ├── editar_registro.php
│   ├── estornar_movimentacao.php
│   ├── excluir_setor.php
│   ├── quitar_pendencia.php
│   ├── realocar_saldo.php
│   ├── recolher_saldo.php
│   ├── salvar_lancamento_completo.php
│   ├── salvar_transacao.php
│   ├── setor_novo.php
│   ├── setor_renomear.php
│   └── transferir_entre_setores.php
│
├── pages/
│   ├── dashboard.php
│   ├── historico.php
│   ├── erro.php
│   │
│   ├── partial/
│   │   ├── sidebar.php
│   │   └── sidebar.css
│   │
│   ├── autentificacao/
│   │   ├── login.php
│   │   └── logout.php
│   │
│   ├── setores/
│   │   └── detalhes.php
│   │
│   └── perfil/
│       ├── perfil.php
│       └── cadastro.php
│
├── assets/
│   ├── dashboard.css
│   ├── historico.css
│   ├── login.css
│   ├── perfil.css
│   └── setores.css
│
├── database/
│   └── banco_my_cash.sql
│
└── docs/
    ├── MAPA_E_ESTRUTURA_MY_CASH.md
    └── Modelagem_Banco_My_Cash.md

# 5. Arquivos partilhados e Camadas

A arquitetura adota uma divisão rigorosa de responsabilidades, estruturada de forma semelhante ao padrão MVC (Model-View-Controller) para garantir segurança e escalabilidade.

## `config/conexao.php` — BACK-END

* cria uma única conexão PDO com a base de dados MySQL utilizando `charset=utf8mb4`;


* configura o tratamento de erros como rigoroso através da diretiva `PDO::ERRMODE_EXCEPTION`, garantindo que os blocos `try-catch` funcionem corretamente em todo o sistema;


* desativa a emulação de prepares (`PDO::ATTR_EMULATE_PREPARES => false`), uma medida crucial para garantir proteção máxima contra injeções de SQL (SQL Injection);


* disponibiliza a variável `$pdo` globalmente.



## `crud/*` — CAMADA DE DADOS (MODEL)

* ficheiros separados por domínio funcional (por exemplo, `crud_setores.php`, `crud_contas_receber.php`);


* centraliza as queries à base de dados (INSERT, UPDATE, SELECT), isolando as operações MySQL da lógica visual e das regras de negócio gerais;


* protege todas as consultas através da função `prepare()` e inclui validações estritas de dados, tais como a utilização de `filter_var` para endereços de e-mail e instâncias `DateTime` para validação de formatos de data.



## `pages/partial/sidebar.php` — FRONT-END (NAV)

* atua como um menu lateral dinâmico e totalmente responsivo;


* contém a lógica de carregamento dos Setores ativos diretamente a partir da base de dados;


* engloba as estruturas HTML das Janelas Modais responsáveis pela criação (`modalNovoSetor`), renomeação (`modalRenomearSetor`) e exclusão (`modalExcluirSetor`) dos setores, tornando estas ações disponíveis de forma instantânea em qualquer parte do sistema.



## `pages/erro.php` — INTERCETADOR DE FALHAS (PROTEÇÃO GLOBAL)

* funciona como o recetor unificado de todas as quebras de regras de negócio;
* recebe os parâmetros `msg` e `link` via URL a partir dos ficheiros da pasta `actions/`;


* exibe um painel de alerta visualmente limpo e amigável ao utilizador sempre que ocorre um erro (como saldo insuficiente, datas inválidas ou restrições da base de dados), oferecendo um botão seguro para regressar ao fluxo do sistema.



---

# 6. Autenticação

## Administrador Inicial Padrão

* O sistema assume, para manter uma base de código enxuta, que a base de dados inicializada via script SQL já inclui um perfil de administrador padrão.
* Dados de acesso iniciais sugeridos pelo script:
* **Nome:** admin
* **E-mail:** admin@gmail.com
* **Senha:** 123


* Com este login garantido no arranque, o administrador utiliza posteriormente as opções de Perfil do sistema para alterar as suas credenciais para algo seguro e para gerir a entrada de novos membros da equipa.

## `pages/autentificacao/login.php` — FRONT-END E BACK-END

* engloba a interface visual e o script de processamento num único ficheiro, mantendo o fluxo de entrada simplificado;


* realiza a busca do administrador através do e-mail e avalia a validade da senha informada recorrendo à função segura `password_verify()` (presente e executada na camada CRUD);


* utiliza `session_regenerate_id(true)` no momento exato em que a autenticação tem sucesso, assegurando total proteção contra roubo e fixação de sessões (Session Fixation);


* redireciona para o Dashboard em caso de sucesso ou renderiza a mensagem de erro diretamente por cima do formulário de acesso caso as credenciais falhem.



## `pages/autentificacao/logout.php` — BACK-END

* encerra a sessão ativa do utilizador, efetuando a limpeza prévia da array global (`$_SESSION = array()`) antes de executar o `session_destroy()`;


* efetua o redirecionamento final e obrigatório para a página de `login.php`.



---

# 7. Dashboard

## `pages/dashboard.php` — VISUAL / AMBOS

O Dashboard constitui o centro nevrálgico da aplicação. Este atua não apenas como um painel de consulta (Hub), mas como o principal ponto de operação financeira através de interações em Modais.

**Exibições e KPIs:**

* Destaque do Saldo Geral (o Caixa global do sistema) lido em tempo real.


* Resumo totalizado das Receitas e Despesas que já foram efetivadas de acordo com o intervalo de datas escolhido.


* Gráfico dinâmico de Evolução Financeira comparativa das entradas e saídas ao longo dos meses (renderizado com a biblioteca Chart.js).



**Painéis de Controlo em Abas (Tabs):**

* **Pendências:** Fornece duas tabelas interativas independentes, separadas em "Contas a Receber" e "Contas a Pagar", ambas munidas de filtros específicos por nome, setor e datas de vencimento.


* **Transferências:** Oferece painéis embutidos para executar rapidamente os três tipos de movimentos internos permitidos pelo sistema: "Recolher para Saldo Geral", "Saldo Geral para Setor", e "Setor para Setor".



**Ações Operacionais Globais:**

* Contém um botão de ação flutuante (FAB) de `Novo Lançamento`, encarregue de invocar a Modal genérica de criação. Esta modal suporta nativamente inserções de Receitas e Despesas, permite escolher se as mesmas nascem "Efetivadas" ou "Pendentes", e lida com a configuração de Múltiplas Parcelas.


* Nas tabelas de itens pendentes, existem botões inline identificados como `Baixar` ou `Pagar`. Ao interagir com eles, o sistema levanta a `modalQuitacao`, pedindo ao utilizador que valide a Data Efetiva e o Valor Final da transação.



---

# 8. Administradores (Perfil)

A gestão completa das contas de acesso encontra-se centralizada sob o diretório `pages/perfil/`.

## `pages/perfil/cadastro.php` — VISUAL / AMBOS

* apresenta um formulário e atua como o seu próprio recetor `POST`, passando os dados validamente formatados para a função `criarAdministrador()` residente na camada CRUD;


* impõe regras estritas para a criação de novos utilizadores: garante que o e-mail não exista ainda no sistema, avalia limites dimensionais de caracteres e aplica requisitos de força para as senhas criadas;


* processa falhas e erros de validação capturando as exceções internas `InvalidArgumentException` ou `DomainException`, mapeando-as de volta para campos visuais no formulário.



## `pages/perfil/perfil.php` — VISUAL / AMBOS

* serve para o administrador em sessão atualizar ativamente os seus dados;
* separa as Informações Básicas (Nome, E-mail) das configurações de Segurança (Nova Senha e respetiva Confirmação);


* submete as informações recebidas por via de um pedido `POST` direcionado para `actions/atualizar_perfil.php`.



## `actions/atualizar_perfil.php` — PROCESSAMENTO / BACK-END

* inspeciona o estado da sessão para confirmar a autenticidade do utilizador em trânsito;


* realiza uma validação preventiva na base de dados (`SELECT id_admin FROM administradores WHERE email = :email AND id_admin != :id`) para proibir sobreposição de e-mails entre contas distintas;


* processa a conversão das novas senhas em hashes cifrados e irreversíveis através da função `password_hash()`, se uma nova senha for fornecida no formulário;


* executa todo o bloco sob uma transação PDO; se ocorrer uma anomalia interna, reverte as ações e redireciona de volta injetando a informação do erro nos parâmetros de query `GET`.



---

# 9. Setores

O módulo Setores opera de forma contínua através de Modais e de um ecrã consolidado de dados detalhados.

## Criação e Edição (Via `partial/sidebar.php` e `actions/`)

* **Novo Setor:** Acionado na Sidebar através do `modalNovoSetor`, os dados são recebidos por `actions/setor_novo.php`. O script confirma os dados e ordena a criação com o seu `saldo_atual` inerentemente a zero na tabela.


* **Renomear:** Conduzido pelo `modalRenomearSetor` (acedido através das opções no menu do setor na Sidebar). O novo nome é transmitido e validado em `actions/setor_renomear.php`.


* **Exclusão Lógica:** Solicitada a partir do `modalExcluirSetor`, a ação vai para o validador em `actions/excluir_setor.php`. A regra de negócio proíbe a eliminação de setores onde o `saldo_atual` seja diferente de R$ 0,00. Se essa restrição for ignorada, o script aborta e envia a justificação explícita ao `erro.php`. Em caso de cumprimento das regras, uma atualização oculta o registo do sistema alterando `ativo = FALSE` em vez de apagar os dados definitivamente.



## `pages/setores/detalhes.php` — VISÃO ISOLADA DE SETOR

* opera como um ambiente focado e exclusivo para analisar os movimentos de uma única secção da empresa;


* evidencia o Saldo Atual Disponível daquele Setor no topo do ecrã com destaque visual;


* consolida a operação num painel centralizado de quatro separadores analíticos:
* **Contas a Receber:** Pendências ativas a favor deste Setor;


* **Contas a Pagar:** Obrigações ou Compromissos futuros da responsabilidade deste Setor;


* **Entradas Efetivadas:** Receitas finalizadas e concretizadas a favor do Setor;


* **Saídas Efetivadas:** Despesas subtraídas da conta e finalizadas pelo Setor;




* contém formulários miniatura em cada aba para ordenar dados livremente (Data mais recente/antiga, Maior/Menor valor) e botões inline # 10. Categorias;

# 10. Categorias

O módulo de Categorias tem como finalidade classificar a origem e o destino do dinheiro. Funciona como um sistema de "tags" obrigatório para todos os lançamentos financeiros do sistema.

## Lógica e Estrutura na Base de Dados

* As categorias são geridas pela tabela `categorias`, possuindo uma coluna estrita `tipo` do tipo `ENUM('RECEITA', 'DESPESA')`.


* A tabela possui também uma coluna `ativo` para permitir a ocultação (soft-delete) de categorias descontinuadas sem quebrar os relatórios antigos.



## Regras de Negócio (`crud/crud_categorias.php`)

* **Criação:** Recebe o nome e o tipo da categoria, validando os limites de caracteres antes de inserir na base de dados.


* **Edição e Restrições:** O sistema verifica ativamente se uma categoria já foi utilizada nalguma operação financeira (`categoriaPossuiRegistrosFinanceiros`). Se a categoria já possuir uma despesa ou receita vinculada, a regra de negócio bloqueia qualquer tentativa de alteração do seu tipo (ex: mudar de 'Receita' para 'Despesa'), garantindo a integridade dos dados passados.


* **Status:** Utiliza as funções `ativarCategoria()` e `desativarCategoria()` para gerir o estado de exibição nos formulários.



---

# 11. Receitas

As páginas isoladas de listagem e criação (`pages/receitas/`) foram abolidas para garantir maior fluidez. O tratamento de receitas ocorre através da Modal de Lançamento Universal.

## Criação (`actions/salvar_lancamento_completo.php`)

O utilizador abre a Modal no Dashboard ou na Visão do Setor e seleciona "Receita" com o status "Efetivado". O fluxo no back-end decorre em bloco atómico:

1. Inicia o `beginTransaction()`.


2. Executa a query `SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE` para bloquear a linha do Caixa e prevenir condições de concorrência.


3. Atualiza matematicamente o Saldo Geral com o novo valor a somar.


4. Cria o registo principal de auditoria na tabela `movimentacoes` com o tipo `RECEITA` e recupera o seu ID (`lastInsertId`).


5. Insere os detalhes da operação na tabela `receitas`, vinculando o `id_movimentacao_fk`, o método de pagamento e o `id_setor_fk` de origem daquela receita.


6. Executa o `commit()`.



## Estorno (`actions/estornar_movimentacao.php`)

Não existe edição de valores para receitas efetivadas. Se houve um erro, a operação deve ser desfeita a partir do botão no ecrã de Histórico.

1. O motor de estorno verifica a tabela `receitas` em busca do ID da movimentação.


2. Devolve o dinheiro à empresa efetuando uma subtração (`UPDATE saldo_geral SET saldo_atual = saldo_atual - :val`).


3. Marca o status da receita e da movimentação como `ESTORNADA` e insere o registo de anulação.



---

# 12. Contas a Receber

O módulo de Contas a Receber absorve todo o dinheiro previsto para entrar na empresa (vendas a prazo, parcelamentos, boletos não faturados).

## Criação e Parcelamento (`actions/salvar_lancamento_completo.php`)

Selecionando "Receita" com o status "Pendente" na Modal, o script PHP aciona um motor de laço de repetição (`for`) caso a opção de Parcelamento esteja assinalada.

* Divide o valor total pelo número de parcelas informadas.


* Incrementa automaticamente um mês na data de vencimento a cada nova iteração do ciclo.


* Insere múltiplas linhas na tabela `contas_receber`, criando identificadores únicos para o grupo daquele parcelamento (`codigo_parcelamento`).



## Edição (`actions/editar_registro.php`)

Enquanto não forem recebidas, as contas a receber podem ter a sua `descricao`, `valor`, `vencimento` e `categoria` editadas através da `modalEditar` presente na página de detalhes do setor.

## Confirmação de Recebimento (`actions/quitar_pendencia.php`)

Quando o utilizador clica no botão `Baixar` na tabela, um painel exige a introdução da Data Efetiva e a confirmação do Valor.

1. O sistema injeta o valor confirmado no Saldo Geral.


2. Gera a Movimentação e a nova Receita associada à obrigação pendente.


3. Modifica o status da Conta a Receber na base de dados para `RECEBIDO`.



---

# 13. Despesas

O módulo de Despesas gere todo o dinheiro que já foi fisicamente retirado das contas dos setores. A sua gestão de ecrãs foi igualmente unificada às rotas globais.

## Criação (`actions/salvar_lancamento_completo.php`)

No formulário de Lançamento Universal, ao optar por "Despesa" e "Efetivado", o sistema processa a dedução imediata.

1. Inicia o bloqueio exclusivo de leitura com `SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE`.


2. **Validação Crítica:** Verifica se o saldo bloqueado é matematicamente superior ou igual ao valor do novo lançamento. Se não for, o script lança uma Exceção (`throw new Exception("Saldo insuficiente no setor selecionado.")`) que aborta toda a transação imediatamente.


3. Se existir cobertura, efetua a dedução e gera a `movimentacao` e a `despesa`.



## Estorno (`actions/estornar_movimentacao.php`)

De forma simétrica às receitas, o erro numa despesa é resolvido pelo botão de desfazer na Auditoria/Histórico.

1. O sistema detecta que a operação a anular é uma Despesa.


2. Recupera o `id_setor_fk` e devolve fisicamente o dinheiro àquele centro de custos (`UPDATE setores SET saldo_atual = saldo_atual + :val`).


3. Invalida os status antigos mudando-os para `ESTORNADA` e preserva o rasto na tabela de movimentações.



---

# 14. Compromissos

A gestão de contas a pagar recai sobre a tabela de Compromissos. Funciona como o espelho exato das Contas a Receber, focado porém nos Setores.

## Criação e Edição

* A criação passa igualmente por `actions/salvar_lancamento_completo.php`, registando o item na tabela `compromissos` sem desencadear deduções no saldo de nenhum setor.


* A edição é suportada pela interface `modalEditar`, sendo direcionada para o `actions/editar_registro.php`. Tal como acontece com os créditos, qualquer compromisso só é livremente editável enquanto o seu status pertencer ao grupo `PENDENTE` ou `ATRASADO`.



## Confirmação de Pagamento (`actions/quitar_pendencia.php`)

A concretização de uma saída financeira a partir de uma promessa de pagamento é o passo mais sensível do sistema de compromissos. O botão "Pagar", disponível nas listagens dinâmicas, submete o ID do compromisso.

1. O processador verifica se o compromisso existe e garante que o seu status ainda não é `PAGO`.


2. Efetua a verificação crucial de cobertura financeira: se o Setor responsável possuir menos dinheiro do que o exigido na quitação final, a transação reverte com a mensagem `"O setor não tem saldo suficiente"`.


3. Tendo saldo aprovado, debita o setor, emite a `movimentacao`, preenche e insere os dados na tabela `despesas` vinculando o pagamento à sua origem através da coluna `id_compromisso_fk`, e marca a obrigação inicial como concluída (`UPDATE compromissos SET status = 'PAGO'`).que permitem invocar os Modais locais de Edição e de Quitação das pendências listadas.



# 15. Transferências

As transferências representam as movimentações de recursos dentro da própria empresa. Como o sistema evoluiu para uma interface centralizada, as antigas páginas dedicadas (`transferencias/index.php` e `nova.php`) foram substituídas por painéis dinâmicos integrados diretamente no `Dashboard`.

## Operações Centralizadas (Ações)

As transferências dividem-se agora em três fluxos distintos, acessíveis pelas abas de transferência no Dashboard:

1. **Recolher Saldo (`actions/recolher_saldo.php`):**
* Transfere o dinheiro do caixa de um Setor de volta para o Saldo Geral.


* **Regra:** Valida se o setor de origem tem fundos suficientes (`saldo_atual < valor` reverte a operação).


* Subtrai o valor do Setor, adiciona ao Saldo Geral e gera uma `movimentacao` do tipo `RECEITA` (neste contexto atua como entrada no caixa geral).




2. **Realocar Saldo (`actions/realocar_saldo.php`):**
* Retira o dinheiro do Saldo Geral e distribui para um Setor específico.


* **Regra:** Bloqueia e verifica a linha do Saldo Geral (`SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE`). Se não houver saldo, lança exceção.


* Retira do Saldo Geral, soma no Setor e cria uma `transferencia` do tipo `DISTRIBUICAO` (e a respetiva movimentação).




3. **Setor para Setor (`actions/transferir_entre_setores.php`):**
* Move dinheiro diretamente de um Setor (Origem) para outro Setor (Destino).


* **Regra:** Valida se a origem e o destino são diferentes e se a origem possui cobertura financeira.


* Executa sob `beginTransaction()`, deduzindo da origem e creditando no destino, gerando uma transferência do tipo `REALOCACAO`.





## Estorno de Transferência

Todas as transferências submetem-se ao ficheiro unificado `actions/estornar_movimentacao.php`. O motor descobre de onde o dinheiro veio (`DISTRIBUICAO` ou `REALOCACAO`) e executa as queries inversas exatas, devolvendo os fundos às suas origens corretas.

---

# 16. Histórico / Movimentações

O módulo de movimentações abandonou a divisão complexa e assumiu a forma de um ecrã consolidado e poderoso de auditoria.

## `pages/historico.php` — VISUAL / AMBOS

* Apresenta uma tabela cronológica completa com Data/Hora, Autor (Nome do Administrador), Tipo, Descrição, Valor e Status de cada operação no sistema.


* Possui filtros de pesquisa avançados no topo da página: por texto (descrição), tipo de operação (`RECEITA`, `DESPESA`, `DISTRIBUICAO`, `REALOCACAO`, `ESTORNO`) e por intervalo de datas (`data_inicio` e `data_fim`).


* **Ação de Reversão:** É o único local do sistema que disponibiliza o botão "Desfazer". Quando a operação subjacente está com o status `ATIVA`, este botão submete um formulário seguro que invoca o `actions/estornar_movimentacao.php`, desfazendo todo o efeito financeiro e carimbando visualmente a linha com a *badge* "Estornada".



---

# 17. Saldo Inicial

A implementação do fluxo de Saldo Inicial tornou-se muito mais limpa e orgânica.

* **Abolição de Página Dedicada:** As antigas páginas e ações (`configuracoes/saldo_inicial.php` e `definir_saldo_inicial.php`) foram descartadas para evitar código descartável.
* **Nova Dinâmica:** Se a empresa inicia a sua utilização do My Cash já possuindo dinheiro, o administrador acede à Modal de **Novo Lançamento** no Dashboard, seleciona **Receita**, classifica como **Efetivado** e utiliza uma descrição como "Caixa Inicial" ou "Aporte Inicial".


* Desta forma, o Saldo Inicial flui pelo sistema com as mesmas validações e os mesmos carimbos de auditoria de uma receita normal, garantindo um rasto 100% íntegro.

---

# 18. Assets

A estrutura de ficheiros de estilo (`CSS`) e scripts (`JS`) acompanhou a modularização visual do sistema, focando-se em componentes e interfaces limpas. O JavaScript passou a viver embutido estrategicamente no final dos ficheiros `.php` relevantes para evitar o carregamento global de scripts desnecessários.

## Estrutura de Estilos Front-End

* `assets/dashboard.css`: Controla o layout central, a grelha de estatísticas (KPIs), as tabelas de listagem, as abas iterativas e as estruturas dos modais genéricos.


* `assets/historico.css`: Estiliza unicamente a página de auditoria, focando-se nas *badges* de estado (Ativa/Estornada, Entradas/Saídas) e na formatação rigorosa da tabela cronológica.


* `assets/login.css`: Define os cartões de autenticação, o posicionamento centralizado e os estilos de alertas (`.erro-geral`).


* `assets/perfil.css`: Customiza a apresentação dos formulários do utilizador, a gestão visual das senhas e os ícones circulares de avatar.


* `assets/setores.css`: Controla as métricas financeiras dentro da visão isolada de cada Setor, incluindo o *layout* de "Saldo Atual" em destaque.


* `pages/partial/sidebar.css`: Controla estritamente a barra de navegação lateral, as animações de *dropdown* e os Modais associados à gestão dos Setores.



---

# 19. Lista resumida dos arquivos (Nova Versão)

Esta é a grelha final que substitui a extensa lista de ficheiros originais. A nova arquitetura utiliza menos ficheiros, mas de forma mais inteligente.

| Arquivo | Tipo | Função |
| --- | --- | --- |
| `config/conexao.php` | Back-end | Estabelece ligação segura com a base de dados via PDO |
| `crud/crud_administradores.php` | Back-end | Funções MySQL para criar, editar, listar e validar contas de acesso |
| `crud/crud_categorias.php` | Back-end | Funções MySQL para gerir o ciclo de vida das Categorias |
| `crud/crud_compromissos.php` | Back-end | Funções MySQL exclusivas para inserção e gestão de Contas a Pagar |
| `crud/crud_contas_receber.php` | Back-end | Funções MySQL exclusivas para obrigações de recebimento pendentes |
| `crud/crud_setores.php` | Back-end | Gestão da árvore de Centros de Custo (Setores) e saldos respetivos |
| `pages/partial/sidebar.php` | Front-end | Estrutura de navegação universal e interatividade dos setores |
| `pages/autentificacao/login.php` | Ambos | Interface de entrada e processamento de credenciais |
| `pages/autentificacao/logout.php` | Back-end | Rotina de anulação da sessão ativa |
| `pages/dashboard.php` | Ambos | Consola principal: KPI's, Lançamentos em Massa e Transferências |
| `pages/perfil/cadastro.php` | Ambos | Formulário blindado para expansão da equipa administrativa |
| `pages/perfil/perfil.php` | Ambos | Interface de manutenção dos dados do próprio utilizador logado |
| `pages/setores/detalhes.php` | Ambos | Secção analítica exclusiva dedicada ao saldo e fluxo de um Setor |
| `pages/historico.php` | Ambos | Visão pericial do fluxo de dinheiro; centro de estornos manuais |
| `pages/erro.php` | Ambos | Intercetador universal de falhas operacionais e de regras de negócio |
| `actions/atualizar_perfil.php` | Back-end | Processa o formulário de perfil e as redefinições de segurança |
| `actions/setor_novo.php` | Back-end | Trata do processo de inicialização de um novo Centro de Custo |
| `actions/setor_renomear.php` | Back-end | Processa e persiste a mudança de nomenclatura de um setor |
| `actions/excluir_setor.php` | Back-end | Realiza o soft-delete validado (saldo == 0) de um setor inativo |
| `actions/salvar_lancamento_completo.php` | Back-end | O principal motor insercional: Receitas, Despesas, Pendências e Parcelas |
| `actions/quitar_pendencia.php` | Back-end | Muta o estado (Pendente -> Faturado), movimenta o dinheiro e altera status |
| `actions/editar_registro.php` | Back-end | Aplica correções lícitas a um registo ainda não liquidado |
| `actions/recolher_saldo.php` | Back-end | Fluxo vertical de retorno financeiro: Setor ➝ Caixa |
| `actions/realocar_saldo.php` | Back-end | Fluxo vertical de investimento: Caixa ➝ Setor |
| `actions/transferir_entre_setores.php` | Back-end | Fluxo horizontal direto: Setor ➝ Setor |
| `actions/estornar_movimentacao.php` | Back-end | Mecanismo de defesa atómica para anulação integral de uma operação |