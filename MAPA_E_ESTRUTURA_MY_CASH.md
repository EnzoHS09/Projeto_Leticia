# MAPA E ESTRUTURA DO PROJETO — MY CASH

> Documento de planejamento para os desenvolvedores do My Cash.  
> Stack: **HTML + CSS + PHP puro + MySQL**.  
> Objetivo: manter o projeto **simples, organizado, completo e implementável por três pessoas**, sem framework ou arquitetura exagerada.

---

# 1. Decisões de arquitetura

## 1.1. Separação principal

- `pages/` → páginas visuais acessadas pelo administrador.
- `actions/` → arquivos PHP que recebem `POST` e executam operações.
- `config/` → configuração geral e conexão com MySQL.
- `includes/` → arquivos compartilhados entre páginas.
- `assets/` → CSS, JavaScript e imagens.
- `database/` → script SQL.
- `docs/` → documentação do projeto.

Fluxo padrão:

```text
PÁGINA VISUAL
      ↓ POST
ARQUIVO DE PROCESSAMENTO
      ↓
MYSQL
      ↓
REDIRECT
      ↓
PÁGINA VISUAL
```

Exemplo:

```text
pages/receitas/nova.php
        ↓
actions/receitas/criar.php
        ↓
MySQL
        ↓
pages/receitas/index.php
```

## 1.2. Operações financeiras efetivadas

Receitas, despesas e transferências já efetivadas não terão edição financeira direta. Se uma operação estiver errada:

```text
operação original
      ↓
   ESTORNO
      ↓
nova operação correta
```

Assim registro, histórico e saldos continuam coerentes.

## 1.3. Receita x conta a receber

```text
DINHEIRO JÁ ENTROU
        ↓
      RECEITA
        ↓
   Saldo Geral
```

```text
DINHEIRO AINDA NÃO ENTROU
        ↓
  CONTA A RECEBER
        ↓
 PENDENTE / ATRASADO
        ↓
confirmar recebimento
        ↓
      RECEITA
        ↓
   Saldo Geral
```

## 1.4. Despesa x compromisso

```text
DINHEIRO JÁ SAIU
        ↓
      DESPESA
        ↓
Saldo do setor diminui
```

```text
DINHEIRO AINDA NÃO SAIU
        ↓
     COMPROMISSO
        ↓
 PENDENTE / ATRASADO
        ↓
confirmar pagamento
        ↓
      DESPESA
        ↓
Saldo do setor diminui
```

Importante: receber uma conta a receber gera **RECEITA**; pagar um compromisso gera **DESPESA**. Não é necessário um tipo separado `PAGAMENTO` no histórico.

## 1.5. Saldo Geral

O Saldo Geral não terá CRUD próprio. Ele será mostrado no Dashboard, alterado pelas operações financeiras e configurado manualmente apenas uma vez no início, se a empresa já possuir dinheiro antes de começar a usar o sistema.

---

# 2. Mapa completo das páginas

```text
index.php
│
├── nenhum administrador cadastrado
│       ↓
│   pages/auth/primeiro_acesso.php
│       ↓
│   actions/auth/criar_primeiro_admin.php
│       ↓
│   pages/auth/login.php
│
├── sem sessão
│       ↓
│   pages/auth/login.php
│       ↓
│   actions/auth/login.php
│       ↓
│   pages/dashboard/index.php
│
└── sessão ativa
        ↓
    pages/dashboard/index.php
        │
        ├── RECEITAS
        │   ├── pages/receitas/index.php
        │   ├── pages/receitas/nova.php
        │   └── pages/receitas/detalhes.php
        │
        ├── CONTAS A RECEBER
        │   ├── pages/contas_receber/index.php
        │   ├── pages/contas_receber/nova.php
        │   ├── pages/contas_receber/editar.php
        │   └── pages/contas_receber/detalhes.php
        │
        ├── DESPESAS
        │   ├── pages/despesas/index.php
        │   ├── pages/despesas/nova.php
        │   └── pages/despesas/detalhes.php
        │
        ├── COMPROMISSOS
        │   ├── pages/compromissos/index.php
        │   ├── pages/compromissos/novo.php
        │   ├── pages/compromissos/editar.php
        │   └── pages/compromissos/detalhes.php
        │
        ├── SETORES
        │   ├── pages/setores/index.php
        │   ├── pages/setores/novo.php
        │   ├── pages/setores/editar.php
        │   └── pages/setores/detalhes.php
        │
        ├── TRANSFERÊNCIAS
        │   ├── pages/transferencias/index.php
        │   ├── pages/transferencias/nova.php
        │   └── pages/transferencias/detalhes.php
        │
        ├── CATEGORIAS
        │   ├── pages/categorias/index.php
        │   ├── pages/categorias/nova.php
        │   └── pages/categorias/editar.php
        │
        ├── ADMINISTRADORES
        │   ├── pages/administradores/index.php
        │   ├── pages/administradores/novo.php
        │   └── pages/administradores/editar.php
        │
        ├── HISTÓRICO
        │   ├── pages/movimentacoes/index.php
        │   └── pages/movimentacoes/detalhes.php
        │
        ├── CONFIGURAÇÃO INICIAL
        │   └── pages/configuracoes/saldo_inicial.php
        │
        └── SAIR
            └── actions/auth/logout.php
```

---

# 3. Navegação

## Menu lateral

```text
Dashboard

Entradas
├── Receitas
└── Contas a receber

Saídas
├── Despesas
└── Compromissos

Gestão financeira
├── Setores
├── Transferências
└── Categorias

Sistema
├── Histórico
└── Administradores

Sair
```

Após login: `Dashboard`.

Páginas de cadastro/edição terão `[Salvar] [Cancelar]`; `Cancelar` volta para a listagem do módulo.

Listagens terão coluna `Ações`, normalmente com `[Ver]` e `[Editar]` quando edição for permitida.

Estorno aparece somente nos detalhes de receita, despesa e transferência e somente se a operação estiver ativa.

Conta a receber recebida mostra link para a receita gerada. Compromisso pago mostra link para a despesa gerada. O estorno ocorre na receita/despesa gerada, evitando dois caminhos diferentes para desfazer a mesma operação.

---

# 4. Estrutura completa de pastas

```text
my-cash/
│
├── index.php
│
├── config/
│   ├── app.php
│   └── conexao.php
│
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── sidebar.php
│   ├── flash.php
│   └── footer.php
│
├── pages/
│   ├── auth/
│   │   ├── login.php
│   │   └── primeiro_acesso.php
│   ├── dashboard/
│   │   └── index.php
│   ├── administradores/
│   │   ├── index.php
│   │   ├── novo.php
│   │   └── editar.php
│   ├── setores/
│   │   ├── index.php
│   │   ├── novo.php
│   │   ├── editar.php
│   │   └── detalhes.php
│   ├── categorias/
│   │   ├── index.php
│   │   ├── nova.php
│   │   └── editar.php
│   ├── receitas/
│   │   ├── index.php
│   │   ├── nova.php
│   │   └── detalhes.php
│   ├── contas_receber/
│   │   ├── index.php
│   │   ├── nova.php
│   │   ├── editar.php
│   │   └── detalhes.php
│   ├── despesas/
│   │   ├── index.php
│   │   ├── nova.php
│   │   └── detalhes.php
│   ├── compromissos/
│   │   ├── index.php
│   │   ├── novo.php
│   │   ├── editar.php
│   │   └── detalhes.php
│   ├── transferencias/
│   │   ├── index.php
│   │   ├── nova.php
│   │   └── detalhes.php
│   ├── movimentacoes/
│   │   ├── index.php
│   │   └── detalhes.php
│   └── configuracoes/
│       └── saldo_inicial.php
│
├── actions/
│   ├── auth/
│   │   ├── criar_primeiro_admin.php
│   │   ├── login.php
│   │   └── logout.php
│   ├── administradores/
│   │   ├── criar.php
│   │   └── atualizar.php
│   ├── setores/
│   │   ├── criar.php
│   │   └── atualizar.php
│   ├── categorias/
│   │   ├── criar.php
│   │   ├── atualizar.php
│   │   └── alterar_status.php
│   ├── receitas/
│   │   ├── criar.php
│   │   └── estornar.php
│   ├── contas_receber/
│   │   ├── criar.php
│   │   ├── atualizar.php
│   │   └── confirmar_recebimento.php
│   ├── despesas/
│   │   ├── criar.php
│   │   └── estornar.php
│   ├── compromissos/
│   │   ├── criar.php
│   │   ├── atualizar.php
│   │   └── confirmar_pagamento.php
│   ├── transferencias/
│   │   ├── criar.php
│   │   └── estornar.php
│   └── configuracoes/
│       └── definir_saldo_inicial.php
│
├── assets/
│   ├── css/
│   │   ├── app.css
│   │   ├── auth.css
│   │   └── dashboard.css
│   ├── js/
│   │   ├── app.js
│   │   └── parcelamento.js
│   └── img/
│
├── database/
│   └── banco_my_cash.sql
│
├── docs/
│   ├── Modelagem_Banco_My_Cash.md
│   └── MAPA_E_ESTRUTURA_MY_CASH.md
│
└── README.md
```

Se o SQL ou a modelagem já existirem em outra pasta, não mover automaticamente: primeiro conferir para não apagar trabalho existente.

---

# 5. Arquivos compartilhados

## `config/app.php` — BACK-END

- inicia sessão;
- define configurações gerais;
- pode guardar `BASE_URL` e timezone.

## `config/conexao.php` — BACK-END

- cria uma única conexão PDO com MySQL;
- configura tratamento de erros;
- disponibiliza `$pdo`.

## `includes/auth.php` — BACK-END

- verifica se existe sessão;
- protege páginas/actions privadas;
- redireciona para login quando necessário.

## `includes/header.php` — FRONT-END

- início do HTML;
- `<head>`;
- CSS compartilhado;
- abertura do layout.

## `includes/sidebar.php` — FRONT-END

- menu lateral;
- links dos módulos;
- item ativo;
- saída.

## `includes/flash.php` — AMBOS

Mostra mensagens após redirects, como:

```text
Receita cadastrada com sucesso.
Saldo insuficiente. Transação não efetuada.
E-mail já cadastrado.
```

## `includes/footer.php` — FRONT-END

- fecha layout/HTML;
- carrega JavaScript compartilhado.

---

# 6. Autenticação

## `index.php` — PROCESSAMENTO / BACK-END

- nenhum administrador → `primeiro_acesso.php`;
- sem sessão → `login.php`;
- com sessão → Dashboard.

## `pages/auth/primeiro_acesso.php` — PÁGINA VISUAL / AMBOS

Campos: nome, e-mail, senha e confirmação.  
Botão: `Criar administrador`.  
POST: `actions/auth/criar_primeiro_admin.php`.

## `actions/auth/criar_primeiro_admin.php` — PROCESSAMENTO / BACK-END

- aceita POST;
- confirma que ainda não existe admin;
- valida campos;
- gera `password_hash()`;
- insere primeiro administrador;
- redireciona para login.

## `pages/auth/login.php` — PÁGINA VISUAL / AMBOS

Campos: e-mail e senha.  
Botão: `Entrar`.  
POST: `actions/auth/login.php`.

## `actions/auth/login.php` — PROCESSAMENTO / BACK-END

- busca administrador pelo e-mail;
- usa `password_verify()`;
- cria sessão;
- redireciona ao Dashboard.

## `actions/auth/logout.php` — PROCESSAMENTO / BACK-END

- encerra sessão;
- volta ao login.

---

# 7. Dashboard

## `pages/dashboard/index.php` — PÁGINA VISUAL / AMBOS

Exibir:

- Saldo Geral;
- soma dos saldos dos setores;
- total consolidado;
- contas a receber pendentes/atrasadas;
- compromissos pendentes/atrasados;
- receitas recentes;
- despesas recentes;
- saldos dos setores;
- últimas movimentações;
- gráficos previstos no protótipo.

Atalhos: `Nova receita`, `Nova conta a receber`, `Nova despesa`, `Novo compromisso`, `Nova transferência`.

O Dashboard consulta e exibe; não deve alterar saldos diretamente.

---

# 8. Administradores

## `pages/administradores/index.php` — VISUAL / AMBOS

Lista nome/e-mail. Botões: `Novo administrador`, `Editar`.

## `pages/administradores/novo.php` — VISUAL / AMBOS

Campos: nome, e-mail, senha, confirmação.  
POST: `actions/administradores/criar.php`.

## `actions/administradores/criar.php` — PROCESSAMENTO / BACK-END

Valida sessão, dados, e-mail único, gera hash e insere.

## `pages/administradores/editar.php` — VISUAL / AMBOS

Campos: nome, e-mail e nova senha opcional.  
POST: `actions/administradores/atualizar.php`.

## `actions/administradores/atualizar.php` — PROCESSAMENTO / BACK-END

Atualiza nome/e-mail e hash somente quando nova senha for informada.

---

# 9. Setores

## `pages/setores/index.php` — VISUAL / AMBOS

Mostra nome, descrição, saldo atual e botões `Ver`, `Editar`, `Novo setor`.

## `pages/setores/novo.php` — VISUAL / AMBOS

Campos: nome e descrição. **Sem campo de saldo.**  
POST: `actions/setores/criar.php`.

## `actions/setores/criar.php` — PROCESSAMENTO / BACK-END

Valida e cria setor com saldo inicial `0`.

## `pages/setores/editar.php` — VISUAL / AMBOS

Edita nome/descrição. Não edita saldo.  
POST: `actions/setores/atualizar.php`.

## `actions/setores/atualizar.php` — PROCESSAMENTO / BACK-END

Atualiza apenas dados cadastrais.

## `pages/setores/detalhes.php` — VISUAL / AMBOS

Mostra nome, descrição, saldo, receitas relacionadas, despesas e transferências do setor. Botões: `Voltar`, `Editar`, e opcionalmente atalho para `Nova transferência`.

---

# 10. Categorias

## `pages/categorias/index.php` — VISUAL / AMBOS

Mostra nome, tipo, ativo/inativo. Botões: `Nova categoria`, `Editar`, `Ativar/Desativar`.

## `pages/categorias/nova.php` — VISUAL / AMBOS

Campos: nome e tipo.  
POST: `actions/categorias/criar.php`.

## `actions/categorias/criar.php` — PROCESSAMENTO / BACK-END

Valida e cria categoria.

## `pages/categorias/editar.php` — VISUAL / AMBOS

Edita nome. Para o MVP, se a categoria já tiver sido usada, não alterar seu tipo.  
POST: `actions/categorias/atualizar.php`.

## `actions/categorias/atualizar.php` — PROCESSAMENTO / BACK-END

Atualiza campos permitidos.

## `actions/categorias/alterar_status.php` — PROCESSAMENTO / BACK-END

Ativa/desativa sem apagar a categoria.

---

# 11. Receitas

## `pages/receitas/index.php` — VISUAL / AMBOS

Lista dinheiro já recebido: data, descrição, categoria, setor relacionado, método, valor e status. Filtros: período, setor, categoria e status. Botões: `Nova receita`, `Ver`.

## `pages/receitas/nova.php` — VISUAL / AMBOS

Somente para dinheiro já recebido.

Campos: descrição, valor, data, método, setor relacionado e categoria RECEITA.

Aviso: se ainda não entrou, usar Contas a Receber.  
POST: `actions/receitas/criar.php`.

## `actions/receitas/criar.php` — PROCESSAMENTO / BACK-END

```text
START TRANSACTION
→ validar dados/categoria
→ criar movimentação RECEITA
→ criar receita
→ aumentar Saldo Geral
→ COMMIT
```

Receita direta: `id_conta_receber_fk = NULL`.

## `pages/receitas/detalhes.php` — VISUAL / AMBOS

Mostra todos os dados, administrador, conta a receber de origem (se houver), movimentação e status. Botões: `Voltar`, `Ver conta de origem` e `Estornar receita` se ATIVA.

## `actions/receitas/estornar.php` — PROCESSAMENTO / BACK-END

- busca receita original;
- exige ATIVA;
- usa valor salvo no banco;
- verifica Saldo Geral suficiente;
- diminui Saldo Geral;
- marca receita/movimentação original como ESTORNADA;
- cria movimentação ESTORNO;
- se veio de conta a receber, volta conta para PENDENTE/ATRASADO e limpa data de recebimento;
- usa transação.

---

# 12. Contas a receber

## `pages/contas_receber/index.php` — VISUAL / AMBOS

Lista descrição, valor, parcela, vencimento, status, método, setor e categoria. Filtros: PENDENTE, ATRASADO, RECEBIDO, período, setor, categoria. Botões: `Nova`, `Ver`, `Editar` enquanto não RECEBIDO.

## `pages/contas_receber/nova.php` — VISUAL / AMBOS

Campos: descrição, valor total, vencimento inicial, método, setor, categoria RECEITA, parcelado sim/não, quantidade de parcelas.

POST: `actions/contas_receber/criar.php`.

## `assets/js/parcelamento.js` — FRONT-END

Mostra/esconde campos e prévia de parcelas. A regra definitiva continua no PHP.

## `actions/contas_receber/criar.php` — PROCESSAMENTO / BACK-END

- valida dados/categoria;
- sem parcelamento → 1 linha PENDENTE;
- em 5x → 5 linhas PENDENTES;
- calcula valores/vencimentos;
- agrupa parcelas quando aplicável;
- usa transação para múltiplas linhas.

## `pages/contas_receber/editar.php` — VISUAL / AMBOS

Somente PENDENTE/ATRASADO. Edita descrição, vencimento, método, setor e categoria. Para o MVP, não reestruturar um parcelamento já criado.  
POST: `actions/contas_receber/atualizar.php`.

## `actions/contas_receber/atualizar.php` — PROCESSAMENTO / BACK-END

Impede edição de RECEBIDO, valida e atualiza campos permitidos.

## `pages/contas_receber/detalhes.php` — VISUAL / AMBOS

Mostra dados completos, parcela, vencimento, status, data de recebimento e receita gerada. Se PENDENTE/ATRASADO: `Editar` e `Confirmar recebimento`. Se RECEBIDO: `Ver receita gerada`.

## `actions/contas_receber/confirmar_recebimento.php` — PROCESSAMENTO / BACK-END

```text
START TRANSACTION
→ buscar/bloquear conta
→ validar PENDENTE/ATRASADO
→ ler valor/setor/categoria do banco
→ criar movimentação RECEITA
→ criar receita com id_conta_receber_fk
→ aumentar Saldo Geral
→ marcar RECEBIDO
→ preencher data_recebimento
→ COMMIT
```

---

# 13. Despesas

## `pages/despesas/index.php` — VISUAL / AMBOS

Lista data, descrição, categoria, setor, método, valor e status. Filtros: período, setor, categoria e status. Botões: `Nova despesa`, `Ver`.

## `pages/despesas/nova.php` — VISUAL / AMBOS

Para gasto que já foi pago. Campos: descrição, valor, data, método, setor, categoria DESPESA. Aviso: se ainda precisa pagar, usar Compromissos.  
POST: `actions/despesas/criar.php`.

## `actions/despesas/criar.php` — PROCESSAMENTO / BACK-END

```text
START TRANSACTION
→ validar dados/categoria
→ buscar/bloquear setor
→ verificar saldo
→ criar movimentação DESPESA
→ criar despesa
→ diminuir saldo do setor
→ COMMIT
```

Despesa direta: `id_compromisso_fk = NULL`.

## `pages/despesas/detalhes.php` — VISUAL / AMBOS

Mostra dados, setor, categoria, administrador, compromisso de origem (se houver), movimentação e status. Botões: `Voltar`, `Ver compromisso de origem`, `Estornar despesa` se ATIVA.

## `actions/despesas/estornar.php` — PROCESSAMENTO / BACK-END

Devolve saldo ao setor, marca operação/movimentação como ESTORNADA, cria ESTORNO e, se veio de compromisso, volta o compromisso para PENDENTE/ATRASADO e limpa data de pagamento.

---

# 14. Compromissos

## `pages/compromissos/index.php` — VISUAL / AMBOS

Lista descrição, valor, vencimento, status, setor e categoria. Filtros: PENDENTE, ATRASADO, PAGO, vencimento, setor e categoria. Botões: `Novo compromisso`, `Ver`, `Editar` enquanto não PAGO.

## `pages/compromissos/novo.php` — VISUAL / AMBOS

Campos: descrição, valor, vencimento, setor e categoria DESPESA. Não reduz saldo.  
POST: `actions/compromissos/criar.php`.

## `actions/compromissos/criar.php` — PROCESSAMENTO / BACK-END

Cria compromisso PENDENTE, sem movimentar saldo.

## `pages/compromissos/editar.php` — VISUAL / AMBOS

Somente PENDENTE/ATRASADO. Campos: descrição, valor, vencimento, setor e categoria.  
POST: `actions/compromissos/atualizar.php`.

## `actions/compromissos/atualizar.php` — PROCESSAMENTO / BACK-END

Impede edição de PAGO e atualiza dados permitidos.

## `pages/compromissos/detalhes.php` — VISUAL / AMBOS

Mostra dados completos, status, data do pagamento e despesa gerada. Se PENDENTE/ATRASADO: `Editar` e `Confirmar pagamento`. Se PAGO: `Ver despesa gerada`.

## `actions/compromissos/confirmar_pagamento.php` — PROCESSAMENTO / BACK-END

```text
START TRANSACTION
→ buscar/bloquear compromisso
→ validar PENDENTE/ATRASADO
→ buscar/bloquear setor
→ verificar saldo
→ criar movimentação DESPESA
→ criar despesa com id_compromisso_fk
→ diminuir saldo do setor
→ marcar PAGO
→ preencher data_pagamento
→ COMMIT
```

---

# 15. Transferências

## `pages/transferencias/index.php` — VISUAL / AMBOS

Lista data, tipo, origem, destino, valor e status. Filtros: DISTRIBUIÇÃO, REALOCAÇÃO, período e status. Botões: `Nova transferência`, `Ver`.

## `pages/transferencias/nova.php` — VISUAL / AMBOS

Primeiro escolhe `Distribuição` ou `Realocação`.

Distribuição: origem fixa = Saldo Geral, destino = setor.  
Realocação: origem = setor, destino = outro setor.  
Campos comuns: valor e data.  
POST: `actions/transferencias/criar.php`.

## `actions/transferencias/criar.php` — PROCESSAMENTO / BACK-END

Distribuição:

```text
bloquear Saldo Geral
→ verificar saldo
→ bloquear destino
→ criar movimentação DISTRIBUICAO
→ criar transferência
→ saldo geral -= valor
→ destino += valor
```

Realocação:

```text
bloquear origem/destino
→ impedir origem = destino
→ verificar saldo
→ criar movimentação REALOCACAO
→ criar transferência
→ origem -= valor
→ destino += valor
```

Tudo em transação.

## `pages/transferencias/detalhes.php` — VISUAL / AMBOS

Mostra tipo, valor, data, origem, destino, administrador, status e movimentação. Botões: `Voltar`, `Estornar transferência` se ATIVA.

## `actions/transferencias/estornar.php` — PROCESSAMENTO / BACK-END

Distribuição: retira do destino e devolve ao Saldo Geral.  
Realocação: retira do antigo destino e devolve à antiga origem.  
Valida saldo, marca original como ESTORNADA, cria ESTORNO e usa transação.

---

# 16. Histórico / movimentações

## `pages/movimentacoes/index.php` — VISUAL / AMBOS

Lista data/hora, tipo, descrição, valor, administrador e status. Filtros: tipo, status, período e administrador. Botão: `Ver`.

Não existe cadastro manual de movimentação.

## `pages/movimentacoes/detalhes.php` — VISUAL / AMBOS

Mostra dados completos, operação correspondente e, em ESTORNO, a movimentação original. Botões: `Voltar`, `Ver operação de origem` quando aplicável.

Não existe botão de estorno diretamente no histórico; o estorno começa na operação financeira original.

---

# 17. Saldo inicial

## `pages/configuracoes/saldo_inicial.php` — VISUAL / AMBOS

Campo: saldo inicial. Botões: `Definir saldo inicial`, `Cancelar`. Só deve ficar disponível enquanto a configuração inicial ainda for permitida.

## `actions/configuracoes/definir_saldo_inicial.php` — PROCESSAMENTO / BACK-END

Valida sessão, confirma que ainda pode configurar, atualiza o registro único do Saldo Geral e impede reconfiguração depois do início normal das operações. Saldo inicial não é receita.

---

# 18. Assets

## `assets/css/app.css` — FRONT-END

Layout geral, tipografia, sidebar, botões, formulários, tabelas, cards e mensagens.

## `assets/css/auth.css` — FRONT-END

Login e primeiro acesso.

## `assets/css/dashboard.css` — FRONT-END

Cards e gráficos do Dashboard.

## `assets/js/app.js` — FRONT-END

Menu responsivo, confirmações e pequenas interações. Regras financeiras nunca dependem exclusivamente do JavaScript.

## `assets/js/parcelamento.js` — FRONT-END

Interface de parcelamento; PHP valida novamente.

---

# 19. Lista resumida dos arquivos

| Arquivo | Tipo | Função |
|---|---|---|
| `index.php` | Back-end | Entrada/redirecionamento |
| `config/app.php` | Back-end | Sessão/configuração |
| `config/conexao.php` | Back-end | PDO/MySQL |
| `includes/auth.php` | Back-end | Proteção de sessão |
| `includes/header.php` | Front-end | Cabeçalho/layout |
| `includes/sidebar.php` | Front-end | Menu lateral |
| `includes/flash.php` | Ambos | Mensagens de retorno |
| `includes/footer.php` | Front-end | Fechamento/scripts |
| `pages/auth/login.php` | Ambos | Tela de login |
| `pages/auth/primeiro_acesso.php` | Ambos | Primeiro administrador |
| `actions/auth/criar_primeiro_admin.php` | Back-end | Cria primeiro admin |
| `actions/auth/login.php` | Back-end | Autentica |
| `actions/auth/logout.php` | Back-end | Encerra sessão |
| `pages/dashboard/index.php` | Ambos | Resumo financeiro |
| `pages/administradores/index.php` | Ambos | Lista admins |
| `pages/administradores/novo.php` | Ambos | Form de admin |
| `pages/administradores/editar.php` | Ambos | Edita admin |
| `actions/administradores/criar.php` | Back-end | Cria admin |
| `actions/administradores/atualizar.php` | Back-end | Atualiza admin |
| `pages/setores/index.php` | Ambos | Lista setores |
| `pages/setores/novo.php` | Ambos | Form setor |
| `pages/setores/editar.php` | Ambos | Edita setor |
| `pages/setores/detalhes.php` | Ambos | Detalha setor |
| `actions/setores/criar.php` | Back-end | Cria setor |
| `actions/setores/atualizar.php` | Back-end | Atualiza setor |
| `pages/categorias/index.php` | Ambos | Lista categorias |
| `pages/categorias/nova.php` | Ambos | Form categoria |
| `pages/categorias/editar.php` | Ambos | Edita categoria |
| `actions/categorias/criar.php` | Back-end | Cria categoria |
| `actions/categorias/atualizar.php` | Back-end | Atualiza categoria |
| `actions/categorias/alterar_status.php` | Back-end | Ativa/desativa |
| `pages/receitas/index.php` | Ambos | Lista receitas |
| `pages/receitas/nova.php` | Ambos | Receita direta |
| `pages/receitas/detalhes.php` | Ambos | Detalha receita |
| `actions/receitas/criar.php` | Back-end | Efetiva receita |
| `actions/receitas/estornar.php` | Back-end | Estorna receita |
| `pages/contas_receber/index.php` | Ambos | Lista valores a receber |
| `pages/contas_receber/nova.php` | Ambos | Form/parcelamento |
| `pages/contas_receber/editar.php` | Ambos | Edita não recebida |
| `pages/contas_receber/detalhes.php` | Ambos | Detalha/confirmar |
| `actions/contas_receber/criar.php` | Back-end | Cria conta(s) |
| `actions/contas_receber/atualizar.php` | Back-end | Atualiza conta |
| `actions/contas_receber/confirmar_recebimento.php` | Back-end | Gera receita e saldo |
| `pages/despesas/index.php` | Ambos | Lista despesas |
| `pages/despesas/nova.php` | Ambos | Despesa direta |
| `pages/despesas/detalhes.php` | Ambos | Detalha despesa |
| `actions/despesas/criar.php` | Back-end | Efetiva despesa |
| `actions/despesas/estornar.php` | Back-end | Estorna despesa |
| `pages/compromissos/index.php` | Ambos | Lista contas a pagar |
| `pages/compromissos/novo.php` | Ambos | Form compromisso |
| `pages/compromissos/editar.php` | Ambos | Edita não pago |
| `pages/compromissos/detalhes.php` | Ambos | Detalha/confirmar |
| `actions/compromissos/criar.php` | Back-end | Cria compromisso |
| `actions/compromissos/atualizar.php` | Back-end | Atualiza compromisso |
| `actions/compromissos/confirmar_pagamento.php` | Back-end | Gera despesa e reduz setor |
| `pages/transferencias/index.php` | Ambos | Lista transferências |
| `pages/transferencias/nova.php` | Ambos | Distribuição/realocação |
| `pages/transferencias/detalhes.php` | Ambos | Detalha transferência |
| `actions/transferencias/criar.php` | Back-end | Altera saldos |
| `actions/transferencias/estornar.php` | Back-end | Desfaz transferência |
| `pages/movimentacoes/index.php` | Ambos | Histórico |
| `pages/movimentacoes/detalhes.php` | Ambos | Detalha movimentação |
| `pages/configuracoes/saldo_inicial.php` | Ambos | Saldo inicial |
| `actions/configuracoes/definir_saldo_inicial.php` | Back-end | Define saldo inicial |
| `assets/css/app.css` | Front-end | CSS geral |
| `assets/css/auth.css` | Front-end | CSS autenticação |
| `assets/css/dashboard.css` | Front-end | CSS Dashboard |
| `assets/js/app.js` | Front-end | Interações simples |
| `assets/js/parcelamento.js` | Front-end | UI de parcelamento |

---

# 20. Divisão Front-end / Back-end

| Módulo | Área principal |
|---|---|
| Layout compartilhado | Front-end |
| Login visual | Front-end |
| Autenticação/sessão | Back-end |
| Dashboard | Ambos |
| Administradores | Ambos |
| Setores | Ambos |
| Categorias | Ambos |
| Receitas | Ambos; lógica crítica no Back-end |
| Contas a receber | Ambos; lógica crítica no Back-end |
| Despesas | Ambos; lógica crítica no Back-end |
| Compromissos | Ambos; lógica crítica no Back-end |
| Transferências | Ambos; lógica crítica no Back-end |
| Estornos | Back-end |
| Histórico | Ambos |
| Saldo inicial | Ambos |
| CSS/responsividade | Front-end |
| PDO/transações MySQL | Back-end |

Branches recomendadas por funcionalidade:

```text
feat/auth
feat/setores-categorias
feat/receitas
feat/contas-receber
feat/despesas-compromissos
feat/transferencias
feat/dashboard
```

---

# 21. Dependências entre funcionalidades

```text
BANCO
  ↓
CONEXÃO PDO
  ↓
AUTENTICAÇÃO / SESSÃO
  ↓
LAYOUT
  │
  ├── SETORES ───────────┐
  └── CATEGORIAS ────────┤
                         ↓
                RECEITAS / DESPESAS

CONTAS A RECEBER
       ↓ confirmar
    RECEITA
       ↓
  SALDO GERAL

COMPROMISSO
       ↓ confirmar
    DESPESA
       ↓
 SALDO DO SETOR

SALDO GERAL + SETORES
       ↓
TRANSFERÊNCIAS
       ↓
 atualiza saldos

RECEITA / DESPESA / TRANSFERÊNCIA
       ↓
 MOVIMENTAÇÕES
       ↓
   HISTÓRICO

OPERAÇÃO EFETIVADA
       ↓
     ESTORNO
       ↓
desfaz efeito + mantém histórico

TODOS OS MÓDULOS
       ↓
    DASHBOARD
```

---

# 22. Ordem de implementação

1. **Estrutura de pastas/arquivos** — todos passam a trabalhar no mesmo padrão.
2. **Conexão PDO** — todo Back-end depende dela.
3. **Autenticação e sessão** — protege o restante.
4. **Layout compartilhado** — evita três layouts diferentes.
5. **Setores** — usados por quase todos os módulos financeiros.
6. **Categorias** — usadas nas entradas e saídas.
7. **Administradores** — CRUD simples após autenticação.
8. **Saldo inicial** — permite testar operações com saldo realista.
9. **Receita direta** — fluxo básico de entrada.
10. **Despesa direta** — fluxo básico de saída.
11. **Contas a receber** — reutiliza o fluxo de receita.
12. **Compromissos** — reutiliza o fluxo de despesa.
13. **Transferências** — depende dos saldos já confiáveis.
14. **Estornos** — depende das operações originais prontas.
15. **Histórico** — depende das movimentações existentes.
16. **Dashboard** — depende de quase todos os módulos.
17. **Testes integrados** — valida tudo em conjunto.

---

# 23. Comandos PowerShell

## Conferir o que já existe

Na raiz do repositório:

```powershell
Get-ChildItem -Recurse
```

Não apague arquivos que seus colegas já tenham criado.

## Criar as pastas sem sobrescrever

```powershell
$pastas = @(
    "config",
    "includes",
    "pages/auth",
    "pages/dashboard",
    "pages/administradores",
    "pages/setores",
    "pages/categorias",
    "pages/receitas",
    "pages/contas_receber",
    "pages/despesas",
    "pages/compromissos",
    "pages/transferencias",
    "pages/movimentacoes",
    "pages/configuracoes",
    "actions/auth",
    "actions/administradores",
    "actions/setores",
    "actions/categorias",
    "actions/receitas",
    "actions/contas_receber",
    "actions/despesas",
    "actions/compromissos",
    "actions/transferencias",
    "actions/configuracoes",
    "assets/css",
    "assets/js",
    "assets/img",
    "database",
    "docs"
)

foreach ($pasta in $pastas) {
    if (-not (Test-Path $pasta)) {
        New-Item -ItemType Directory -Path $pasta | Out-Null
        Write-Host "Criada: $pasta"
    } else {
        Write-Host "Já existe: $pasta"
    }
}
```

## Criar os arquivos vazios sem sobrescrever

```powershell
$arquivos = @(
    "index.php",
    "config/app.php",
    "config/conexao.php",
    "includes/auth.php",
    "includes/header.php",
    "includes/sidebar.php",
    "includes/flash.php",
    "includes/footer.php",
    "pages/auth/login.php",
    "pages/auth/primeiro_acesso.php",
    "pages/dashboard/index.php",
    "pages/administradores/index.php",
    "pages/administradores/novo.php",
    "pages/administradores/editar.php",
    "pages/setores/index.php",
    "pages/setores/novo.php",
    "pages/setores/editar.php",
    "pages/setores/detalhes.php",
    "pages/categorias/index.php",
    "pages/categorias/nova.php",
    "pages/categorias/editar.php",
    "pages/receitas/index.php",
    "pages/receitas/nova.php",
    "pages/receitas/detalhes.php",
    "pages/contas_receber/index.php",
    "pages/contas_receber/nova.php",
    "pages/contas_receber/editar.php",
    "pages/contas_receber/detalhes.php",
    "pages/despesas/index.php",
    "pages/despesas/nova.php",
    "pages/despesas/detalhes.php",
    "pages/compromissos/index.php",
    "pages/compromissos/novo.php",
    "pages/compromissos/editar.php",
    "pages/compromissos/detalhes.php",
    "pages/transferencias/index.php",
    "pages/transferencias/nova.php",
    "pages/transferencias/detalhes.php",
    "pages/movimentacoes/index.php",
    "pages/movimentacoes/detalhes.php",
    "pages/configuracoes/saldo_inicial.php",
    "actions/auth/criar_primeiro_admin.php",
    "actions/auth/login.php",
    "actions/auth/logout.php",
    "actions/administradores/criar.php",
    "actions/administradores/atualizar.php",
    "actions/setores/criar.php",
    "actions/setores/atualizar.php",
    "actions/categorias/criar.php",
    "actions/categorias/atualizar.php",
    "actions/categorias/alterar_status.php",
    "actions/receitas/criar.php",
    "actions/receitas/estornar.php",
    "actions/contas_receber/criar.php",
    "actions/contas_receber/atualizar.php",
    "actions/contas_receber/confirmar_recebimento.php",
    "actions/despesas/criar.php",
    "actions/despesas/estornar.php",
    "actions/compromissos/criar.php",
    "actions/compromissos/atualizar.php",
    "actions/compromissos/confirmar_pagamento.php",
    "actions/transferencias/criar.php",
    "actions/transferencias/estornar.php",
    "actions/configuracoes/definir_saldo_inicial.php",
    "assets/css/app.css",
    "assets/css/auth.css",
    "assets/css/dashboard.css",
    "assets/js/app.js",
    "assets/js/parcelamento.js",
    "README.md"
)

foreach ($arquivo in $arquivos) {
    if (-not (Test-Path $arquivo)) {
        New-Item -ItemType File -Path $arquivo | Out-Null
        Write-Host "Criado: $arquivo"
    } else {
        Write-Host "Mantido, já existe: $arquivo"
    }
}
```

O script usa `Test-Path` e não usa `-Force`, portanto arquivos existentes são mantidos.

## Conferir depois

```powershell
tree /F
```

---

# 24. Padrão obrigatório das actions

Cada action privada deve seguir, conceitualmente:

```text
1. validar sessão
2. aceitar o método correto, normalmente POST
3. validar dados
4. buscar do banco valores financeiros que não devem ser confiados ao formulário
5. usar prepared statements
6. usar transação quando várias alterações dependem umas das outras
7. COMMIT se tudo funcionar
8. ROLLBACK se algo falhar
9. criar mensagem flash
10. redirecionar
```

Actions não devem renderizar páginas HTML completas. Regras críticas não devem ficar apenas no JavaScript.

---

# 25. Páginas/arquivos que não devem ser criados

```text
editar_receita.php
editar_despesa.php
editar_transferencia.php
nova_movimentacao.php
editar_movimentacao.php
excluir_movimentacao.php
logout_visual.php
saldo_geral_crud.php
```

Motivos:

- operações financeiras efetivadas usam estorno;
- movimentação é histórico automático;
- logout é uma action;
- Saldo Geral é controlado pelas operações.

---

# 26. Revisão dos fluxos críticos

## Receita direta

```text
Nova receita
→ POST
→ movimentação RECEITA
→ receita
→ Saldo Geral aumenta
→ detalhes/listagem
```

**Fechado: SIM.**

## Conta a receber

```text
Nova conta
→ PENDENTE
→ pode ficar ATRASADA
→ confirmar recebimento
→ RECEBIDO
→ gera receita
→ gera movimentação RECEITA
→ Saldo Geral aumenta
```

**Fechado: SIM.**

## Estorno de recebimento

```text
Conta RECEBIDA
→ abrir receita gerada
→ estornar receita
→ receita ESTORNADA
→ Saldo Geral diminui
→ conta volta PENDENTE/ATRASADO
```

**Fechado: SIM.**

## Despesa direta

```text
Nova despesa
→ verificar saldo do setor
→ movimentação DESPESA
→ despesa
→ saldo do setor diminui
```

**Fechado: SIM.**

## Compromisso

```text
Novo compromisso
→ PENDENTE
→ pode ficar ATRASADO
→ confirmar pagamento
→ PAGO
→ gera despesa
→ movimentação DESPESA
→ saldo do setor diminui
```

**Fechado: SIM.**

## Estorno de pagamento

```text
Compromisso PAGO
→ abrir despesa gerada
→ estornar despesa
→ saldo do setor aumenta
→ compromisso volta PENDENTE/ATRASADO
```

**Fechado: SIM.**

## Distribuição

```text
Saldo Geral
→ verificar saldo
→ transferência DISTRIBUICAO
→ movimentação
→ Saldo Geral diminui
→ setor aumenta
```

**Fechado: SIM.**

## Realocação

```text
Setor origem
→ verificar saldo
→ transferência REALOCACAO
→ movimentação
→ origem diminui
→ destino aumenta
```

**Fechado: SIM.**

## Estorno de transferência

```text
Transferência ATIVA
→ verificar saldo de quem devolverá
→ inverter saldos
→ transferência ESTORNADA
→ movimentação original ESTORNADA
→ nova movimentação ESTORNO
```

**Fechado: SIM.**

---

# 27. Checklist antes do PHP

- [ ] banco SQL executa corretamente;
- [ ] campos/tabelas conferem com a modelagem;
- [ ] estrutura foi criada sem apagar arquivos existentes;
- [ ] protótipo visual foi encaixado nessas páginas;
- [ ] todos usam os mesmos nomes de arquivos;
- [ ] conexão PDO funciona;
- [ ] sessão funciona;
- [ ] menu aponta apenas para páginas existentes;
- [ ] formulários usam POST;
- [ ] actions estão separadas das páginas visuais;
- [ ] cada funcionalidade é desenvolvida/testada em branch própria;
- [ ] merge na `main` somente depois de testar.

---

# 28. Conclusão

A estrutura final separa claramente:

```text
VISUAL                  → pages/
PROCESSAMENTO           → actions/
CONFIGURAÇÃO/CONEXÃO    → config/
LAYOUT COMPARTILHADO    → includes/
CSS/JS/IMAGENS          → assets/
BANCO                    → database/
DOCUMENTAÇÃO             → docs/
```

Ela cobre login, primeiro acesso, Dashboard, administradores, setores, categorias, receitas, contas a receber, despesas, compromissos, transferências, movimentações, saldo inicial, confirmações, estornos, navegação, dependências e ordem de implementação, sem criar páginas duplicadas ou uma arquitetura desnecessariamente complexa para PHP puro.
