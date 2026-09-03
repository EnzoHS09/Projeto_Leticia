# Modelagem do Banco de Dados — My Cash v4

Este documento descreve a versão 4 da modelagem do My Cash e deve ser usado junto de `banco_my_cash_v4.sql`.

A v4 mantém as 10 tabelas do projeto, corrige as inconsistências das versões anteriores e deixa o banco mais simples. O MySQL protege a estrutura; o PHP executa as regras de negócio que envolvem várias tabelas, saldos e mudanças de status.

O SQL é um script de criação inicial para um schema vazio. Ele não converte automaticamente um banco v2 ou v3 que já possua tabelas ou dados.

## 1. Decisões principais da v4

- Receita é dinheiro que realmente entrou.
- Despesa é dinheiro que realmente saiu.
- Conta a receber é dinheiro que ainda pode entrar.
- Compromisso é dinheiro que ainda precisa sair.
- O pagamento de um compromisso cria uma `despesa` e uma movimentação `DESPESA`.
- Não existe um tipo separado para pagamento em `movimentacoes`.
- Toda coluna que é chave estrangeira termina com `_fk`.
- Operações efetivadas não são apagadas nem editadas para corrigir valor.
- Uma correção financeira é feita por estorno e novo cadastro.
- O estorno cria outra movimentação, do tipo `ESTORNO`, ligada à movimentação original.
- Os saldos ficam armazenados em `saldo_geral.saldo_atual` e `setores.saldo_atual`.
- O PHP altera os saldos dentro de transações.
- Não há `CHECK`, gatilho ou regra complexa escondida no SQL.
- Não há exclusão automática em cascata.

## 2. Responsabilidade do banco e do PHP

### O MySQL garante

- chaves primárias;
- chaves estrangeiras;
- campos obrigatórios com `NOT NULL`;
- valores não repetidos nos `UNIQUE` importantes;
- conjuntos simples de opções com `ENUM`;
- valores iniciais com `DEFAULT`;
- integridade das relações entre registros existentes.

### O PHP garante

- valor maior que zero;
- saldo suficiente;
- categoria correta e ativa em novos cadastros;
- transições válidas de status;
- coerência de parcelas;
- uma única efetivação ativa por conta ou compromisso;
- correspondência entre operação e movimentação;
- atualização conjunta de operação, histórico e saldo;
- regras de distribuição, realocação e estorno;
- bloqueio contra duas confirmações simultâneas.

Essa separação é intencional. Regras que precisam consultar outras linhas ou alterar várias tabelas não foram transformadas em dezenas de constraints.

## 3. Visão geral das tabelas

| Tabela | O que representa |
|---|---|
| `administradores` | Pessoas que acessam e operam o sistema. |
| `setores` | Áreas da empresa e o saldo atual de cada uma. |
| `categorias` | Classificações de receita ou despesa. |
| `saldo_geral` | Dinheiro disponível que ainda não foi distribuído. |
| `contas_receber` | Valores futuros que a empresa espera receber. |
| `compromissos` | Valores futuros que a empresa precisa pagar. |
| `movimentacoes` | Histórico central de operações e estornos. |
| `receitas` | Entradas de dinheiro já efetivadas. |
| `despesas` | Saídas de dinheiro já efetivadas. |
| `transferencias` | Distribuições e realocações internas. |

## 4. Convenção dos identificadores

Uma chave primária identifica uma linha da própria tabela e não usa `_fk`:

```text
setores.id_setor
movimentacoes.id_movimentacao
```

Uma chave estrangeira aponta para outra linha e sempre usa `_fk`:

```text
despesas.id_setor_fk
        ↓
setores.id_setor
```

Lista de colunas FK da v4:

- `id_admin_fk`
- `id_setor_fk`
- `id_categoria_fk`
- `id_conta_receber_fk`
- `id_compromisso_fk`
- `id_movimentacao_fk`
- `id_movimentacao_origem_fk`
- `id_setor_origem_fk`
- `id_setor_destino_fk`

## 5. Tabelas e campos

### 5.1 `administradores`

Armazena as contas de acesso e preserva a autoria das operações.

| Campo | Regra | Significado |
|---|---|---|
| `id_admin` | PK, automático | Identificador do administrador. |
| `nome` | obrigatório | Nome exibido pelo sistema. |
| `email` | obrigatório, único | Login do administrador. |
| `senha_hash` | obrigatório | Hash criado com `password_hash()`, nunca a senha pura. |
| `ativo` | padrão `TRUE` | Define se a pessoa ainda pode acessar o sistema. |

Um administrador com histórico não deve ser apagado. Para remover seu acesso, o PHP define `ativo = FALSE`.

### 5.2 `setores`

Armazena as áreas da empresa e o dinheiro disponível em cada uma.

| Campo | Regra | Significado |
|---|---|---|
| `id_setor` | PK, automático | Identificador do setor. |
| `nome` | obrigatório, único | Nome do setor. |
| `descricao` | opcional | Explicação sobre o setor. |
| `saldo_atual` | padrão `0.00` | Valor disponível agora. |
| `ativo` | padrão `TRUE` | Define se o setor pode ser usado em novos cadastros. |

`saldo_atual` não é um valor livre do formulário. Ele muda somente por despesa, distribuição, realocação ou estorno.

Uma receita relacionada a um setor serve para classificação, mas aumenta o Saldo Geral, não o saldo daquele setor.

Antes de inativar um setor, o PHP deve verificar se seu saldo é zero e se não existem contas ou compromissos com status `PENDENTE` ou `ATRASADO` ligados a ele. Um setor inativo continua disponível para consultas históricas e estornos.

### 5.3 `categorias`

Classifica entradas e saídas.

| Campo | Regra | Significado |
|---|---|---|
| `id_categoria` | PK, automático | Identificador da categoria. |
| `nome` | obrigatório | Nome exibido ao administrador. |
| `tipo` | `RECEITA` ou `DESPESA` | Lado financeiro da categoria. |
| `ativo` | padrão `TRUE` | Disponibilidade para novos cadastros. |

O par `(nome, tipo)` é único. Assim, pode existir uma categoria chamada `Outros` para receita e outra para despesa, mas não duas iguais do mesmo tipo.

O PHP deve aplicar:

- `receitas` e `contas_receber` usam categoria `RECEITA`;
- `despesas` e `compromissos` usam categoria `DESPESA`;
- categoria inativa não aparece em novo cadastro;
- uma categoria antiga continua válida para concluir ou estornar um registro já existente;
- o tipo de uma categoria já utilizada não deve ser alterado.

### 5.4 `saldo_geral`

Guarda o dinheiro que pertence à empresa e ainda não foi distribuído a setores.

| Campo | Regra | Significado |
|---|---|---|
| `id_saldo_geral` | PK | Identificador do único saldo. |
| `saldo_atual` | padrão `0.00` | Valor disponível no Saldo Geral. |

O SQL cria uma linha:

```text
id_saldo_geral = 1
saldo_atual = 0.00
```

O PHP sempre consulta e bloqueia a linha de ID 1. A regra de uma única linha fica no PHP para evitar mais uma constraint especial no banco.

A v4 começa em zero. Se no futuro o projeto precisar importar dinheiro que já existia antes do sistema, isso deverá ganhar uma regra explícita de saldo inicial. Não se deve alterar `saldo_atual` manualmente sem origem documentada.

### 5.5 `contas_receber`

Representa dinheiro que ainda não entrou. Criar uma conta a receber não muda nenhum saldo.

| Campo | Regra | Significado |
|---|---|---|
| `id_conta_receber` | PK, automático | Identificador da conta. |
| `descricao` | obrigatório | Origem esperada do dinheiro. |
| `valor` | obrigatório | Valor esperado. |
| `vencimento` | obrigatório | Data prevista para receber. |
| `status` | padrão `PENDENTE` | `PENDENTE`, `RECEBIDO` ou `ATRASADO`. |
| `codigo_parcelamento` | opcional | Código comum às parcelas do mesmo grupo. |
| `numero_parcela` | opcional | Número da parcela dentro do grupo. |
| `total_parcelas` | opcional | Quantidade total de parcelas. |
| `id_setor_fk` | FK obrigatória | Setor relacionado para classificação. |
| `id_categoria_fk` | FK obrigatória | Categoria de receita. |
| `id_admin_fk` | FK obrigatória | Administrador que criou a conta. |

Não há `data_recebimento` nem método real nesta tabela. Quando o dinheiro entrar, a data e o método serão armazenados na `receita` que efetivou o recebimento. Isso evita duas cópias do mesmo fato.

#### Parcelas

Uma venda de R$ 1.000 em três parcelas gera três linhas, todas inicialmente pendentes:

```text
VENDA-ABC | 1/3 | R$ 333,33
VENDA-ABC | 2/3 | R$ 333,33
VENDA-ABC | 3/3 | R$ 333,34
```

O par `(codigo_parcelamento, numero_parcela)` é único. Para uma conta não parcelada, os três campos de parcelamento ficam `NULL`.

Para uma conta parcelada, o PHP deve exigir os três campos preenchidos, `numero_parcela >= 1`, `numero_parcela <= total_parcelas` e o mesmo `total_parcelas` em todas as linhas do grupo.

O PHP deve trabalhar em centavos inteiros. Em R$ 1.000 divididos por 3, ele distribui o centavo restante em uma das parcelas para que a soma continue exatamente R$ 1.000.

### 5.6 `compromissos`

Representa uma obrigação que ainda não saiu do caixa. Criar um compromisso não muda o saldo do setor.

| Campo | Regra | Significado |
|---|---|---|
| `id_compromisso` | PK, automático | Identificador do compromisso. |
| `descricao` | obrigatório | Explicação da obrigação. |
| `valor` | obrigatório | Valor que deverá ser pago. |
| `vencimento` | obrigatório | Data limite. |
| `status` | padrão `PENDENTE` | `PENDENTE`, `PAGO` ou `ATRASADO`. |
| `id_setor_fk` | FK obrigatória | Setor que pagará a obrigação. |
| `id_categoria_fk` | FK obrigatória | Categoria de despesa. |
| `id_admin_fk` | FK obrigatória | Administrador que criou o compromisso. |

Não há `data_pagamento` nem método real nesta tabela. A data e o método ficam na `despesa` criada quando o pagamento realmente ocorrer.

### 5.7 `movimentacoes`

É o histórico central. Toda operação efetivada cria uma movimentação.

| Campo | Regra | Significado |
|---|---|---|
| `id_movimentacao` | PK, automático | Identificador do evento. |
| `tipo` | ENUM obrigatório | `RECEITA`, `DESPESA`, `DISTRIBUICAO`, `REALOCACAO` ou `ESTORNO`. |
| `valor` | obrigatório | Valor do evento. |
| `criado_em` | data e hora automáticas | Momento em que o sistema registrou o evento. |
| `descricao` | obrigatório | Resumo legível do evento. |
| `status` | padrão `ATIVA` | `ATIVA` ou `ESTORNADA`. |
| `id_admin_fk` | FK obrigatória | Administrador que executou a ação. |
| `id_movimentacao_origem_fk` | FK opcional e única | Movimento original desfeito por um estorno. |

`criado_em` não é a data financeira da receita, despesa ou transferência. Ele é o horário de auditoria do sistema.

Uma operação normal usa `id_movimentacao_origem_fk = NULL`. Uma movimentação `ESTORNO` aponta para a movimentação original. Como essa FK é `UNIQUE`, a mesma origem não pode receber dois estornos.

O PHP ainda deve impedir:

- movimentação normal com origem preenchida;
- estorno sem origem;
- uma movimentação apontar para si própria;
- estornar outro estorno;
- usar a mesma movimentação em mais de uma tabela financeira.

### 5.8 `receitas`

Representa dinheiro que já entrou e aumenta o Saldo Geral.

| Campo | Regra | Significado |
|---|---|---|
| `id_receita` | PK, automático | Identificador da receita. |
| `descricao` | obrigatório | Origem do dinheiro. |
| `valor` | obrigatório | Valor que entrou. |
| `data` | obrigatório | Data real do recebimento. |
| `metodo_pagamento` | ENUM obrigatório | Método real usado. |
| `status` | padrão `ATIVA` | `ATIVA` ou `ESTORNADA`. |
| `id_setor_fk` | FK obrigatória | Setor relacionado para classificação. |
| `id_categoria_fk` | FK obrigatória | Categoria de receita. |
| `id_admin_fk` | FK obrigatória | Administrador que registrou ou confirmou. |
| `id_conta_receber_fk` | FK opcional | Conta que originou a receita. |
| `id_movimentacao_fk` | FK obrigatória e única | Movimento correspondente. |

Receita direta:

```text
id_conta_receber_fk = NULL
```

Receita criada ao confirmar uma conta:

```text
id_conta_receber_fk = id da conta confirmada
```

`id_conta_receber_fk` não é `UNIQUE`. Depois de um estorno, a mesma conta pode voltar a ser recebida e gerar outra receita. O PHP garante que exista no máximo uma receita `ATIVA` para aquela conta.

### 5.9 `despesas`

Representa dinheiro que já saiu e reduz o saldo de um setor.

| Campo | Regra | Significado |
|---|---|---|
| `id_despesa` | PK, automático | Identificador da despesa. |
| `descricao` | obrigatório | Motivo da saída. |
| `valor` | obrigatório | Valor que saiu. |
| `data` | obrigatório | Data real da saída. |
| `metodo_pagamento` | ENUM obrigatório | Método real usado. |
| `status` | padrão `ATIVA` | `ATIVA` ou `ESTORNADA`. |
| `id_setor_fk` | FK obrigatória | Setor cujo saldo diminuiu. |
| `id_categoria_fk` | FK obrigatória | Categoria de despesa. |
| `id_admin_fk` | FK obrigatória | Administrador que registrou ou confirmou. |
| `id_compromisso_fk` | FK opcional | Compromisso que originou a despesa. |
| `id_movimentacao_fk` | FK obrigatória e única | Movimento correspondente. |

Despesa direta:

```text
id_compromisso_fk = NULL
movimentacao.tipo = DESPESA
```

Pagamento de compromisso:

```text
id_compromisso_fk = id do compromisso pago
movimentacao.tipo = DESPESA
```

A FK preenchida já informa que aquela despesa veio de um compromisso. Não é necessário criar outro tipo de movimentação.

`id_compromisso_fk` não é `UNIQUE`, pois um pagamento estornado pode ser realizado de novo. O PHP garante no máximo uma despesa `ATIVA` por compromisso.

### 5.10 `transferencias`

Representa deslocamentos internos de dinheiro. Não é receita nem despesa, pois o dinheiro continua pertencendo à empresa.

| Campo | Regra | Significado |
|---|---|---|
| `id_transferencia` | PK, automático | Identificador da transferência. |
| `tipo` | ENUM obrigatório | `DISTRIBUICAO` ou `REALOCACAO`. |
| `valor` | obrigatório | Valor transferido. |
| `data` | obrigatório | Data financeira da transferência. |
| `status` | padrão `ATIVA` | `ATIVA` ou `ESTORNADA`. |
| `id_setor_origem_fk` | FK opcional | Setor que enviou numa realocação. |
| `id_setor_destino_fk` | FK obrigatória | Setor que recebeu. |
| `id_admin_fk` | FK obrigatória | Administrador que executou. |
| `id_movimentacao_fk` | FK obrigatória e única | Movimento correspondente. |

Para `DISTRIBUICAO`:

```text
origem = Saldo Geral
id_setor_origem_fk = NULL
id_setor_destino_fk = setor que recebe
```

Para `REALOCACAO`:

```text
id_setor_origem_fk = setor que envia
id_setor_destino_fk = setor que recebe
```

O PHP garante que, numa realocação, origem e destino estejam preenchidos e sejam diferentes.

## 6. Métodos financeiros padronizados

`receitas.metodo_pagamento` e `despesas.metodo_pagamento` aceitam:

```text
PIX
DINHEIRO
CARTAO_CREDITO
CARTAO_DEBITO
BOLETO
TRANSFERENCIA
OUTRO
```

Esses valores evitam variações como `Pix`, `pix`, `cartao` e `Crédito` no banco.

## 7. Fluxos que o PHP deve executar

Todos os fluxos desta seção devem usar uma transação. Se qualquer etapa falhar, o PHP executa `ROLLBACK`; somente depois de todas as etapas corretas executa `COMMIT`.

### 7.1 Receita direta

1. Validar valor, setor, categoria e administrador.
2. Bloquear `saldo_geral` de ID 1.
3. Criar uma movimentação `RECEITA`.
4. Criar a receita com `id_conta_receber_fk = NULL`.
5. Usar o mesmo valor na receita e na movimentação.
6. Aumentar `saldo_geral.saldo_atual` pelo mesmo valor.
7. Confirmar a transação.

### 7.2 Confirmar uma conta a receber

1. Receber do formulário apenas o ID da conta, a data real, o método real e os dados necessários à ação.
2. Bloquear a conta com `SELECT ... FOR UPDATE`.
3. Confirmar que ela está `PENDENTE` ou `ATRASADO`.
4. Buscar do banco o valor, a descrição, o setor e a categoria; não confiar em cópias desses valores enviadas pelo navegador.
5. Verificar se não existe outra receita `ATIVA` para a mesma conta.
6. Bloquear o Saldo Geral.
7. Criar movimentação `RECEITA`.
8. Criar receita com `id_conta_receber_fk` preenchida.
9. Alterar a conta para `RECEBIDO`.
10. Aumentar o Saldo Geral.
11. Confirmar a transação.

Esta v4 considera quitação integral: o valor da receita deve ser igual ao valor da conta.

### 7.3 Despesa direta

1. Validar valor, setor, categoria, método e administrador.
2. Bloquear o setor.
3. Verificar saldo suficiente.
4. Criar movimentação `DESPESA`.
5. Criar despesa com `id_compromisso_fk = NULL`.
6. Diminuir o saldo do setor.
7. Confirmar a transação.

### 7.4 Pagar um compromisso

1. Receber o ID do compromisso, a data real e o método real.
2. Bloquear o compromisso e o setor com `SELECT ... FOR UPDATE`.
3. Confirmar que o compromisso está `PENDENTE` ou `ATRASADO`.
4. Buscar do banco o valor, a descrição, o setor e a categoria.
5. Verificar se não existe outra despesa `ATIVA` para o mesmo compromisso.
6. Verificar saldo suficiente no setor.
7. Criar movimentação `DESPESA`.
8. Criar despesa com `id_compromisso_fk` preenchida.
9. Alterar o compromisso para `PAGO`.
10. Diminuir o saldo do setor.
11. Confirmar a transação.

Esta v4 considera quitação integral: o valor da despesa deve ser igual ao valor do compromisso.

### 7.5 Distribuir do Saldo Geral para um setor

1. Validar que o setor destino existe e está ativo.
2. Bloquear o Saldo Geral e o setor destino.
3. Verificar valor positivo e saldo suficiente no Saldo Geral.
4. Criar movimentação `DISTRIBUICAO`.
5. Criar transferência `DISTRIBUICAO`, com origem `NULL`.
6. Diminuir o Saldo Geral.
7. Aumentar o saldo do setor destino.
8. Confirmar a transação.

### 7.6 Realocar entre setores

1. Validar valor positivo e confirmar que os setores existem, estão ativos e são diferentes.
2. Bloquear os dois setores sempre na ordem crescente dos IDs, reduzindo risco de deadlock.
3. Verificar saldo suficiente no setor origem.
4. Criar movimentação `REALOCACAO`.
5. Criar transferência com origem e destino preenchidos.
6. Diminuir a origem e aumentar o destino pelo mesmo valor.
7. Confirmar a transação.

## 8. Estorno

Estornar não é apagar. A operação original continua no banco, marcada como `ESTORNADA`, e uma nova movimentação registra que ela foi desfeita.

### 8.1 Regras gerais

Antes de estornar, o PHP deve:

- bloquear a operação e a movimentação original;
- confirmar que ambas estão `ATIVA`;
- confirmar que a movimentação original não é `ESTORNO`;
- confirmar que ainda não existe estorno para ela;
- ler o valor original do banco;
- nunca permitir que o usuário digite o valor do estorno;
- bloquear os saldos afetados;
- verificar saldo suficiente no lugar de onde o estorno retirará dinheiro.

Ao bloquear saldos, todos os fluxos devem seguir a mesma ordem: primeiro o Saldo Geral, quando envolvido, e depois os setores em ordem crescente de ID. Isso reduz o risco de deadlock.

Na mesma transação:

1. desfazer o efeito no saldo;
2. marcar a operação original como `ESTORNADA`;
3. marcar a movimentação original como `ESTORNADA`;
4. criar uma movimentação `ESTORNO`, `ATIVA`;
5. preencher `id_movimentacao_origem_fk` com o ID original;
6. reabrir conta ou compromisso, quando houver;
7. confirmar tudo junto.

Não existe estorno de estorno. Para repetir uma operação desfeita, cria-se uma nova receita, despesa ou transferência com uma nova movimentação.

### 8.2 Efeito de cada estorno

| Operação original | O estorno faz |
|---|---|
| Receita | Diminui o Saldo Geral. |
| Despesa | Devolve o valor ao setor. |
| Distribuição | Retira do setor destino e devolve ao Saldo Geral. |
| Realocação | Retira do antigo destino e devolve à antiga origem. |

Para estornar receita, distribuição ou realocação, o saldo do local que perderá dinheiro precisa ser suficiente.

Um setor inativo não bloqueia a devolução causada por estorno, pois o histórico precisa poder ser desfeito corretamente. Se o estorno deixar saldo em um setor inativo, o administrador deverá reativá-lo antes de fazer uma nova realocação desse valor.

### 8.3 Conta ou compromisso depois do estorno

Se a receita veio de uma conta a receber:

- volta para `ATRASADO` se o vencimento já passou;
- volta para `PENDENTE` caso contrário.

Se a despesa veio de um compromisso:

- volta para `ATRASADO` se o vencimento já passou;
- volta para `PENDENTE` caso contrário.

Depois disso, a obrigação pode ser efetivada novamente, gerando uma nova operação e uma nova movimentação.

## 9. Status vencidos

O dia do vencimento não prova que o dinheiro entrou ou saiu. Ele apenas indica a data esperada.

O PHP deve atualizar os status ao abrir as listagens ou por uma tarefa periódica:

```sql
UPDATE contas_receber
SET status = 'ATRASADO'
WHERE status = 'PENDENTE'
  AND vencimento < CURDATE();

UPDATE compromissos
SET status = 'ATRASADO'
WHERE status = 'PENDENTE'
  AND vencimento < CURDATE();
```

No próprio cadastro, se o vencimento informado já for anterior à data atual, o PHP deve salvar o registro diretamente como `ATRASADO`, em vez de depender da próxima atualização.

O administrador não escolhe livremente o status em um menu. O status resulta do fluxo:

```text
conta: PENDENTE → ATRASADO → RECEBIDO
compromisso: PENDENTE → ATRASADO → PAGO
```

Após estorno, o PHP recalcula `PENDENTE` ou `ATRASADO` pela data.

## 10. Consistência entre operação, movimentação e saldo

Uma receita, despesa ou transferência repete alguns dados da movimentação correspondente. Essa cópia facilita consultas e preserva um histórico legível, mas exige uma regra rígida:

```text
operacao.valor = movimentacao.valor = valor aplicado ao saldo
```

Também deve haver correspondência de tipo:

```text
receita       → RECEITA
despesa       → DESPESA
distribuicao  → DISTRIBUICAO
realocacao    → REALOCACAO
```

O `UNIQUE(id_movimentacao_fk)` impede duas receitas de usarem a mesma movimentação, por exemplo. Entretanto, apenas constraints simples não impedem que um PHP errado tente usar o mesmo movimento uma vez em `receitas` e outra em `despesas`. Por isso:

- o PHP cria uma movimentação nova para cada operação;
- a operação é criada imediatamente na mesma transação;
- o tipo e o valor são conferidos antes do `COMMIT`;
- uma movimentação normal pertence a exatamente uma operação;
- não se reaproveita ID de movimentação.

### Operações efetivadas são imutáveis

Depois de efetivada, não se altera diretamente valor, data, setor, categoria, método, origem, destino ou FK da movimentação em:

- `receitas`;
- `despesas`;
- `transferencias`.

Se algo estiver errado:

```text
estorna a operação errada
        ↓
cria uma nova operação correta
```

Essa regra evita o problema em que a receita mostra R$ 5.000, a movimentação mostra R$ 1.000 e o saldo recebeu R$ 1.000.

Contas e compromissos ainda não efetivados podem ser corrigidos pelo PHP. Depois de `RECEBIDO` ou `PAGO`, qualquer correção exige primeiro o estorno da operação ligada.

## 11. Concorrência e bloqueios

Uma transação sozinha não impede duas requisições de lerem o mesmo status antes de uma delas gravar. Para receber uma conta, pagar um compromisso, alterar saldo ou estornar, o PHP usa `SELECT ... FOR UPDATE`.

Exemplo conceitual:

```sql
START TRANSACTION;

SELECT status, valor
FROM compromissos
WHERE id_compromisso = ?
FOR UPDATE;

-- validar, criar movimentação e despesa, alterar saldo e status

COMMIT;
```

Enquanto a primeira transação trabalha, a segunda aguarda. Quando continuar, verá o status novo e não fará uma duplicação.

Em qualquer erro ou exceção:

```sql
ROLLBACK;
```

## 12. Exclusão e preservação de histórico

As FKs do SQL não usam exclusão automática em cascata. Ao omitir a ação, o InnoDB bloqueia a exclusão de um registro pai que ainda esteja referenciado.

Exemplos:

- administrador antigo → `ativo = FALSE`;
- setor encerrado → `ativo = FALSE`;
- categoria antiga → `ativo = FALSE`;
- operação financeira efetivada → estorno, nunca `DELETE`;
- movimentação → nunca apagada pelo sistema normal.

Uma FK não impede o PHP de apagar diretamente uma linha filha sem dependentes. Portanto, a aplicação também deve proibir `DELETE` de receitas, despesas, transferências e movimentações.

## 13. Conferência dos saldos

Os saldos armazenados facilitam o dashboard, mas podem ser conferidos pelo histórico de operações `ATIVA`.

Como a v4 começa em zero:

```text
Saldo Geral esperado
= receitas ATIVAS
- distribuições ATIVAS
```

Para cada setor:

```text
Saldo esperado
= distribuições ATIVAS recebidas
+ realocações ATIVAS recebidas
- realocações ATIVAS enviadas
- despesas ATIVAS
```

Movimentações `ESTORNO` são eventos de auditoria. Não devem ser somadas novamente numa conferência que já ignora as operações originais `ESTORNADA`, pois isso contaria o desfazimento duas vezes.

Se o valor calculado e `saldo_atual` divergirem, houve falha de implementação ou alteração manual e o problema deve ser investigado.

## 14. Limites assumidos nesta versão

Para manter o projeto simples, a v4 não tenta resolver estes casos:

- recebimento parcial;
- pagamento parcial;
- estorno parcial;
- juros ou descontos aplicados na confirmação;
- cancelamento separado de conta ou compromisso;
- devolução de um setor para o Saldo Geral;
- várias empresas no mesmo banco;
- saldo inicial diferente de zero sem uma regra própria.

Esses itens não devem ser improvisados com os tipos existentes. Se virarem requisitos, a modelagem deve ser ampliada conscientemente.

## 15. Ordem de criação no SQL

O arquivo cria as tabelas nesta ordem para que cada tabela referenciada já exista:

```text
administradores
setores
categorias
saldo_geral
contas_receber
compromissos
movimentacoes
receitas
despesas
transferencias
```

A autorreferência de `movimentacoes.id_movimentacao_origem_fk` é válida dentro do próprio `CREATE TABLE`.

## 16. Checklist antes de implementar o PHP

- [ ] Toda operação usa transação.
- [ ] Registros e saldos alterados são bloqueados com `FOR UPDATE`.
- [ ] Valores financeiros usam `DECIMAL(15,2)` no banco e centavos inteiros nos cálculos PHP.
- [ ] O valor de conta, compromisso e estorno é relido do banco.
- [ ] Categoria e setor são validados.
- [ ] Operação e movimentação recebem tipo e valor correspondentes.
- [ ] O saldo é alterado pelo mesmo valor da operação.
- [ ] Não há edição direta de operação efetivada.
- [ ] Não há exclusão física de histórico financeiro.
- [ ] Existe no máximo uma receita ativa por conta.
- [ ] Existe no máximo uma despesa ativa por compromisso.
- [ ] O mesmo movimento não é ligado a duas operações.
- [ ] Estorno não pode apontar para outro estorno.
- [ ] Itens vencidos são atualizados para `ATRASADO`.
- [ ] Administradores, setores e categorias inativos são filtrados em novos cadastros.

## 17. Resumo final dos efeitos financeiros

| Ação | Saldo Geral | Setor origem | Setor destino |
|---|---:|---:|---:|
| Receita | aumenta | não muda | não muda |
| Despesa | não muda | diminui | não se aplica |
| Distribuição | diminui | não se aplica | aumenta |
| Realocação | não muda | diminui | aumenta |
| Estorno de receita | diminui | não muda | não muda |
| Estorno de despesa | não muda | aumenta | não se aplica |
| Estorno de distribuição | aumenta | não se aplica | diminui |
| Estorno de realocação | não muda | aumenta | diminui |

Essa é a regra central da v4: o banco preserva as relações e o histórico; o PHP aplica cada mudança completa, uma única vez e dentro de uma transação.
