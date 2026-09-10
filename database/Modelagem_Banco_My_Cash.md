# MODELAGEM DO BANCO DE DADOS — MY CASH

> Documento de apoio para os desenvolvedores do My Cash.
> Este arquivo explica a finalidade das tabelas, o significado dos campos e as principais regras de funcionamento do banco.
> Os tipos SQL (`INT`, `VARCHAR`, `DECIMAL`, `DATE` etc.), `CREATE TABLE`, chaves e demais comandos de criação ficarão em um arquivo `.sql` separado.

## Visão geral

O banco de dados do My Cash está organizado, nesta etapa, em 10 tabelas principais:

1. `administradores`
2. `setores`
3. `categorias`
4. `receitas`
5. `despesas`
6. `compromissos`
7. `transferencias`
8. `movimentacoes`
9. `contas_receber`
10. `saldo_geral`

### Ideia principal do controle financeiro

- `receitas` representam dinheiro que **realmente entrou** na empresa.
- `despesas` representam dinheiro que **realmente saiu** da empresa.
- `contas_receber` representam dinheiro que a empresa **ainda tem para receber**.
- `compromissos` representam dinheiro que a empresa **ainda tem para pagar**.
- Toda receita aumenta primeiro o `saldo_geral`.
- Os setores recebem dinheiro por meio de distribuição do Saldo Geral ou realocação de outro setor.
- Cada setor possui um `saldo_atual`, controlado automaticamente pelo sistema.
- O administrador não altera livremente os saldos para simular uma movimentação. As alterações devem ocorrer pelas operações financeiras do sistema.

---

## 1 - administradores

### Finalidade

Armazenar as contas das pessoas que poderão acessar e administrar o My Cash.
O sistema não terá usuários comuns. Todos os usuários serão administradores.

### Campos

- `id_admin`
- `nome`
- `email`
- `senha_hash`

### Explicação dos campos

#### `id_admin`

- Chave primária (PK) da tabela.
- É o identificador único de cada administrador.
- Cada administrador possuirá um `id_admin` diferente.
- Esse ID poderá ser utilizado por outras tabelas para registrar qual administrador realizou determinada operação.

Exemplo:

```text
id_admin = 1
nome = Enzo

id_admin = 2
nome = Danilo
```

#### `nome`

- Armazena o nome do administrador.
- É utilizado para identificar a pessoa dentro do sistema.

#### `email`

- Armazena o e-mail utilizado pelo administrador para acessar o sistema.
- Deve ser único, para impedir a existência de duas contas com o mesmo e-mail.

#### `senha_hash`

- Armazena a senha do administrador de forma protegida.
- A senha verdadeira não deve ser salva diretamente no banco.
- No cadastro, o PHP utilizará `password_hash()` para gerar o hash da senha.
- No login, o PHP utilizará `password_verify()` para verificar se a senha digitada corresponde ao hash armazenado.

---

## 2 - setores

### Finalidade

Armazenar os setores existentes na empresa.
Cada setor representa uma área da empresa que poderá receber recursos e ter receitas, despesas e outras movimentações financeiras relacionadas.

### Campos

- `id_setor`
- `nome`
- `descricao`
- `saldo_atual`

### Explicação dos campos

#### `id_setor`

- Chave primária (PK) da tabela.
- É o número que identifica cada setor de forma única no banco de dados.
- Cada setor possuirá um `id_setor` diferente.
- Esse ID será utilizado por outras tabelas para indicar qual setor está relacionado a determinado registro.

Exemplo:

```text
id_setor = 1
nome = Comercial

id_setor = 2
nome = Marketing
```

Se uma despesa possuir:

```text
id_setor = 2
```

significa que essa despesa está relacionada ao setor Marketing.

#### `nome`

- Armazena o nome do setor.
- É o nome que aparecerá para o administrador dentro do sistema.

Exemplos:

- Comercial
- Financeiro
- Marketing
- Recursos Humanos

#### `descricao`

- Armazena uma explicação sobre a função ou finalidade daquele setor.
- Serve para deixar mais claro o que aquele setor representa dentro da empresa.
- Não interfere diretamente nos cálculos financeiros.

Exemplo:

```text
nome = Marketing
descricao = "Setor responsável pela divulgação e publicidade da empresa."
```

#### `saldo_atual`

- Armazena quanto dinheiro aquele setor possui disponível naquele momento.
- Quando um setor for criado, o `saldo_atual` começará em `0`.
- O administrador não altera esse valor diretamente.
- O próprio sistema atualiza o saldo conforme distribuições, despesas e realocações.
- Esse campo permite mostrar rapidamente quanto dinheiro o setor possui sem recalcular todo o histórico sempre que uma página for aberta.

Exemplo:

```text
Marketing criado:
saldo_atual = 0

Distribuição de R$ 5.000 para Marketing:
saldo_atual = 5.000

Despesa de R$ 1.500 paga pelo Marketing:
saldo_atual = 3.500

Realocação recebida de R$ 2.000:
saldo_atual = 5.500

Realocação de R$ 500 para outro setor:
saldo_atual = 5.000
```

### Importante

- Uma receita relacionada a um setor **não aumenta diretamente** o `saldo_atual` daquele setor.
- Toda receita entra primeiro no Saldo Geral.
- O setor só recebe dinheiro quando ocorre uma distribuição do Saldo Geral ou uma realocação vinda de outro setor.

Exemplo:

```text
Receita relacionada ao Comercial: R$ 10.000

Saldo Geral:
+ R$ 10.000

Comercial:
nenhuma alteração no saldo_atual
```

Depois, se o administrador distribuir R$ 4.000:

```text
Saldo Geral:
- R$ 4.000

Comercial:
+ R$ 4.000
```

### Resumo do funcionamento do saldo do setor

- Criação do setor → começa em `0`.
- Distribuição recebida → saldo aumenta.
- Realocação recebida → saldo aumenta.
- Despesa realizada → saldo diminui.
- Realocação enviada → saldo diminui.
- Receita registrada → não altera diretamente o saldo do setor.

---

## 3 - categorias

### Finalidade

Armazenar as categorias utilizadas para organizar e classificar valores de entrada e saída.
A categoria ajuda a explicar de onde veio o dinheiro ou com o que ele foi gasto.

### Campos

- `id_categoria`
- `nome`
- `tipo`
- `ativo`

### Explicação dos campos

#### `id_categoria`

- Chave primária (PK) da tabela.
- É o número que identifica cada categoria de forma única no banco de dados.
- Duas categorias diferentes nunca terão o mesmo `id_categoria`.
- Esse ID será utilizado por outras tabelas para indicar qual categoria está relacionada ao registro.

Exemplo:

```text
id_categoria = 1
nome = Vendas

id_categoria = 2
nome = Energia
```

Se uma despesa possuir:

```text
id_categoria = 2
```

significa que essa despesa pertence à categoria Energia.

#### `nome`

- Armazena o nome da categoria.
- É o nome mostrado ao administrador ao cadastrar uma receita, despesa, compromisso ou conta a receber.

Exemplos de categorias de RECEITA:

- Vendas
- Serviços
- Outras Receitas

Exemplos de categorias de DESPESA:

- Salários
- Aluguel
- Água
- Energia
- Internet
- Fornecedores
- Impostos
- Outras Despesas

#### `tipo`

- Indica se a categoria pertence às entradas de dinheiro (`RECEITA`) ou às saídas de dinheiro (`DESPESA`).
- Serve para o sistema saber em quais cadastros aquela categoria poderá aparecer.
- Os valores previstos são `RECEITA` e `DESPESA`.

Exemplo:

```text
nome = Vendas
tipo = RECEITA
```

Isso significa que Vendas poderá ser utilizada em receitas e contas a receber.

Outro exemplo:

```text
nome = Energia
tipo = DESPESA
```

Isso significa que Energia poderá ser utilizada em despesas e compromissos.

O sistema poderá filtrar as categorias assim:

Ao cadastrar uma RECEITA ou CONTA A RECEBER:

- Vendas
- Serviços
- Outras Receitas

Ao cadastrar uma DESPESA ou COMPROMISSO:

- Salários
- Energia
- Aluguel
- Fornecedores

#### `ativo`

- Indica se a categoria ainda está disponível para ser utilizada em novos registros.
- Permite desativar uma categoria sem apagá-la do banco.
- Isso é importante porque registros antigos podem continuar utilizando aquela categoria.

Exemplo:

```text
ativo = 1
```

- Categoria ativa.
- Pode ser utilizada em novos registros.

```text
ativo = 0
```

- Categoria desativada.
- Não deve aparecer para novos registros.
- Continua existindo para preservar registros antigos.

---

## 4 - receitas

### Finalidade

Registrar dinheiro que **realmente entrou** na empresa.

Toda receita deverá estar relacionada a um setor para fins de organização, mas o dinheiro recebido não será colocado diretamente no saldo desse setor.
O valor aumenta primeiro o Saldo Geral.

### Campos

- `id_receita`
- `descricao`
- `valor`
- `data`
- `metodo_pagamento`
- `id_setor`
- `id_categoria`
- `id_admin`
- `id_conta_receber`

### Explicação dos campos

#### `id_receita`

- Chave primária (PK) da tabela.
- Identifica cada receita de forma única.

#### `descricao`

- Explica a origem do dinheiro recebido.

Exemplo:

```text
"Venda de produtos."
```

#### `valor`

- Armazena o valor que efetivamente entrou na empresa.

Exemplo:

```text
5000.00
```

#### `data`

- Armazena a data em que o dinheiro foi efetivamente recebido.

#### `metodo_pagamento`

- Armazena a forma pela qual o dinheiro foi recebido.

Exemplos:

- PIX
- Dinheiro
- Cartão de crédito
- Cartão de débito
- Boleto
- Transferência

#### `id_setor`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica qual setor está relacionado à origem daquela receita.
- Essa relação é utilizada para organização e relatórios.
- O valor da receita continua entrando primeiro no Saldo Geral.

Exemplo:

```text
Receita: R$ 5.000
id_setor = 1
```

Se `id_setor = 1` for Comercial, significa que a receita está relacionada ao Comercial.
Isso **não** significa que R$ 5.000 serão adicionados ao saldo do Comercial.

#### `id_categoria`

- Chave estrangeira (FK).
- Referencia o `id_categoria` da tabela `categorias`.
- Indica a categoria daquela receita.

Exemplo:

```text
Receita: R$ 5.000
Setor: Comercial
Categoria: Vendas
```

O setor indica a área da empresa relacionada ao recebimento.
A categoria explica a origem financeira do dinheiro.

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite identificar qual administrador registrou a receita.
- É utilizado para rastreabilidade.

#### `id_conta_receber`

- Chave estrangeira (FK).
- Referencia o `id_conta_receber` da tabela `contas_receber`.
- Indica se a receita foi gerada pelo recebimento de uma conta que estava pendente.
- Esse campo poderá ser `NULL` quando a receita for recebida imediatamente e não existir uma conta a receber anterior.

Exemplo de recebimento imediato:

```text
Venda recebida por PIX na hora.
id_conta_receber = NULL
```

Exemplo de recebimento futuro:

```text
Parcela de R$ 200 estava em contas_receber.
A parcela foi recebida.
É criada uma receita de R$ 200 ligada àquela conta a receber.
```

### Importante

- `receitas` não possui campo de vencimento.
- Se o dinheiro ainda não entrou, ele não é uma receita efetivada: deve ficar em `contas_receber`.
- Quando uma conta a receber for paga, o sistema registra a receita correspondente e aumenta o Saldo Geral.

Fluxo:

```text
Dinheiro ainda não recebido
        ↓
contas_receber
        ↓
recebimento confirmado
        ↓
receita
        ↓
Saldo Geral aumenta
```

---

## 5 - despesas

### Finalidade

Registrar dinheiro que **realmente saiu** da empresa.

Cada despesa ficará relacionada a um setor e reduzirá o `saldo_atual` desse setor.

### Campos

- `id_despesa`
- `descricao`
- `valor`
- `data`
- `metodo_pagamento`
- `id_setor`
- `id_categoria`
- `id_admin`
- `id_compromisso`

### Explicação dos campos

#### `id_despesa`

- Chave primária (PK) da tabela.
- Identifica cada despesa de forma única.

#### `descricao`

- Explica com o que o dinheiro foi gasto.

Exemplo:

```text
"Pagamento da conta de energia."
```

#### `valor`

- Armazena o valor que efetivamente saiu da empresa.

#### `data`

- Armazena a data em que o pagamento foi efetivamente realizado.

#### `metodo_pagamento`

- Armazena a forma utilizada para realizar o pagamento.

Exemplos:

- PIX
- Dinheiro
- Cartão
- Boleto
- Transferência

#### `id_setor`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica qual setor terá seu `saldo_atual` reduzido.

Exemplo:

```text
Despesa: R$ 800
Setor: Financeiro
```

Resultado:

```text
saldo_atual do Financeiro = saldo anterior - R$ 800
```

Se o setor não possuir saldo suficiente, a despesa não deverá ser efetivada.

#### `id_categoria`

- Chave estrangeira (FK).
- Referencia o `id_categoria` da tabela `categorias`.
- Indica qual é a categoria daquela despesa.

Exemplos:

```text
Conta de energia → Categoria: Energia
Pagamento dos funcionários → Categoria: Salários
```

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite registrar qual administrador cadastrou a despesa.
- É utilizado para rastreabilidade.

#### `id_compromisso`

- Chave estrangeira (FK).
- Referencia o `id_compromisso` da tabela `compromissos`.
- Indica se a despesa foi gerada pelo pagamento de um compromisso existente.
- Esse campo poderá ser `NULL` quando a despesa tiver sido paga imediatamente, sem compromisso anterior.

Exemplo de despesa sem compromisso:

```text
Compra de material de escritório paga na hora.
id_compromisso = NULL
```

Exemplo de despesa originada de compromisso:

```text
Conta de energia estava registrada como compromisso.
Quando foi paga, foi criada a despesa correspondente.
```

### Importante

- `despesas` não precisa guardar o vencimento como regra principal.
- Se existe uma obrigação que ainda será paga, o vencimento fica em `compromissos`.
- Quando o compromisso for pago, ele gera a despesa correspondente.

Fluxo:

```text
Dinheiro ainda não pago
        ↓
compromissos
        ↓
pagamento realizado
        ↓
despesa
        ↓
saldo_atual do setor diminui
```

---

## 6 - compromissos

### Finalidade

Registrar obrigações financeiras que a empresa precisa pagar, mas que ainda podem não ter sido efetivamente pagas.

Um compromisso não representa dinheiro que já saiu.

### Campos

- `id_compromisso`
- `descricao`
- `valor`
- `vencimento`
- `status`
- `id_setor`
- `id_categoria`
- `id_admin`

### Explicação dos campos

#### `id_compromisso`

- Chave primária (PK) da tabela.
- Identifica cada compromisso financeiro de forma única.

#### `descricao`

- Explica qual obrigação deverá ser paga.

Exemplo:

```text
"Conta de energia de setembro."
```

#### `valor`

- Armazena o valor que deverá ser pago.
- O valor ainda não é retirado do saldo do setor enquanto o compromisso não for pago.

#### `vencimento`

- Armazena a data limite para pagamento.
- É utilizado para identificar quando uma obrigação está pendente ou atrasada.

#### `status`

- Armazena a situação atual do compromisso.

Valores previstos:

- `PENDENTE`
- `PAGO`
- `ATRASADO`

Funcionamento:

```text
Ainda não venceu e não foi pago → PENDENTE
Passou do vencimento e não foi pago → ATRASADO
Pagamento realizado → PAGO
```

#### `id_setor`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica qual setor será responsável pelo pagamento.
- Quando o compromisso for pago, o valor sairá do saldo desse setor.

#### `id_categoria`

- Chave estrangeira (FK).
- Referencia o `id_categoria` da tabela `categorias`.
- Indica a categoria financeira do compromisso.
- Deve utilizar uma categoria do tipo `DESPESA`.

Exemplo:

```text
Compromisso: Conta de energia
Categoria: Energia
```

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite saber qual administrador cadastrou o compromisso.

### Diferença entre compromisso e despesa

Compromisso:

- É uma obrigação que ainda precisa ser paga.
- Pode possuir vencimento futuro.
- Não reduz o saldo enquanto não for pago.

Despesa:

- Representa dinheiro que realmente saiu.
- Reduz o saldo do setor.

Exemplo:

```text
31/08:
Conta de energia de R$ 800 com vencimento para 10/09.
→ existe um COMPROMISSO
→ o dinheiro ainda não saiu

10/09:
A conta é paga.
→ compromisso passa para PAGO
→ é registrada uma DESPESA de R$ 800
→ saldo do setor diminui R$ 800
```

Nem toda despesa precisa possuir um compromisso anterior.
Uma compra paga imediatamente pode ser registrada diretamente como despesa.

---

## 7 - transferencias

### Finalidade

Registrar movimentações de recursos dentro da própria empresa.

Transferências não representam despesas porque o dinheiro continua pertencendo à empresa.

Existem dois tipos principais:

```text
DISTRIBUIÇÃO:
Saldo Geral → Setor

REALOCAÇÃO:
Setor → Outro setor
```

### Campos

- `id_transferencia`
- `tipo`
- `valor`
- `data`
- `id_setor_origem`
- `id_setor_destino`
- `id_admin`

### Explicação dos campos

#### `id_transferencia`

- Chave primária (PK) da tabela.
- Identifica cada transferência de forma única.

#### `tipo`

- Indica qual tipo de transferência foi realizada.

Valores previstos:

- `DISTRIBUICAO`
- `REALOCACAO`

`DISTRIBUICAO`:

- O dinheiro sai do Saldo Geral e é colocado em um setor.

Exemplo:

```text
Saldo Geral → Comercial
```

`REALOCACAO`:

- O dinheiro sai de um setor e é colocado em outro.

Exemplo:

```text
Marketing → Financeiro
```

#### `valor`

- Armazena o valor transferido.

#### `data`

- Armazena a data em que a transferência foi realizada.

#### `id_setor_origem`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica de qual setor saiu o dinheiro em uma realocação.
- Poderá ser `NULL` quando a operação for uma distribuição, pois o Saldo Geral não é um setor.

Exemplo de realocação:

```text
Marketing → Financeiro
id_setor_origem = Marketing
```

Exemplo de distribuição:

```text
Saldo Geral → Comercial
id_setor_origem = NULL
```

#### `id_setor_destino`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica o setor que recebeu os recursos.

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite saber qual administrador realizou a transferência.

### Efeito nos saldos

Distribuição:

```text
Saldo Geral diminui
Saldo do setor destino aumenta
```

Realocação:

```text
Saldo do setor origem diminui
Saldo do setor destino aumenta
```

Antes de concluir a operação, o sistema deverá verificar se a origem possui saldo suficiente.

---

## 8 - movimentacoes

### Finalidade

Manter o histórico financeiro das operações realizadas no My Cash.

A tabela permite registrar o que aconteceu com o dinheiro e qual administrador realizou a operação.

### Campos iniciais

- `id_movimentacao`
- `tipo`
- `valor`
- `data`
- `descricao`
- `id_admin`

### Explicação dos campos

#### `id_movimentacao`

- Chave primária (PK) da tabela.
- Identifica cada movimentação do histórico de forma única.

#### `tipo`

- Indica qual operação gerou o registro.

Exemplos:

- `RECEITA`
- `DESPESA`
- `PAGAMENTO`
- `DISTRIBUICAO`
- `REALOCACAO`

#### `valor`

- Armazena o valor envolvido na movimentação.

#### `data`

- Armazena a data em que a movimentação aconteceu.

#### `descricao`

- Armazena uma descrição que ajuda a entender o que aconteceu.

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite identificar qual administrador realizou ou registrou a operação.

### Observação importante

- A tabela `movimentacoes` representa o histórico e não substitui as tabelas específicas como `receitas`, `despesas` e `transferencias`.
- A forma exata de relacionar cada movimentação ao seu registro de origem ainda deverá ser fechada antes da criação definitiva do SQL.
- Não é recomendado adicionar várias chaves estrangeiras sem decidir essa estrutura, pois isso pode gerar duplicação ou muitos campos vazios.

---

## 9 - contas_receber

### Finalidade

Armazenar valores que a empresa tem direito de receber, mas que ainda não entraram efetivamente no caixa.

Essa tabela permite controlar pagamentos futuros, como:

- vendas parceladas no cartão de crédito;
- boletos;
- pagamentos combinados para uma data futura;
- outros valores que ainda serão recebidos.

Uma conta a receber **não aumenta o Saldo Geral enquanto estiver pendente**.

### Campos

- `id_conta_receber`
- `descricao`
- `valor`
- `vencimento`
- `status`
- `metodo_pagamento`
- `numero_parcela`
- `total_parcelas`
- `data_recebimento`
- `id_setor`
- `id_categoria`
- `id_admin`

### Explicação dos campos

#### `id_conta_receber`

- Chave primária (PK) da tabela.
- É o identificador único de cada valor que a empresa ainda tem para receber.
- Cada parcela poderá ser registrada como uma conta a receber diferente.

Exemplo:

```text
id_conta_receber = 1
Parcela 1 de 5

id_conta_receber = 2
Parcela 2 de 5
```

#### `descricao`

- Armazena uma explicação sobre o valor que deverá ser recebido.

Exemplos:

- Venda de produtos.
- Parcela de venda realizada no cartão.
- Pagamento de serviço prestado.

#### `valor`

- Armazena o valor que a empresa ainda deverá receber.
- Enquanto a conta estiver pendente ou atrasada, esse valor não faz parte do Saldo Geral.

#### `vencimento`

- Armazena a data prevista para o recebimento.
- Permite saber quando aquele dinheiro deveria entrar na empresa.
- Também permite identificar valores atrasados.

#### `status`

- Indica a situação atual da conta a receber.

Valores previstos:

- `PENDENTE`
- `RECEBIDO`
- `ATRASADO`

Funcionamento:

```text
Ainda não venceu e não foi recebido → PENDENTE
Passou do vencimento e não foi recebido → ATRASADO
Dinheiro recebido → RECEBIDO
```

#### `metodo_pagamento`

- Indica de que forma aquele valor deverá ser recebido.

Exemplos:

- `CARTAO_CREDITO`
- `BOLETO`
- `PIX`
- `TRANSFERENCIA`
- `OUTRO`

#### `numero_parcela`

- Indica qual é o número daquela parcela.
- É utilizado quando um recebimento foi dividido em várias parcelas.
- Poderá ser `NULL` quando não existir parcelamento.

Exemplo:

```text
numero_parcela = 2
```

Significa que aquele registro representa a segunda parcela.

#### `total_parcelas`

- Indica em quantas parcelas o recebimento foi dividido.
- Poderá ser `NULL` quando não existir parcelamento.

Exemplo:

```text
numero_parcela = 2
total_parcelas = 5
```

Significa: parcela 2 de 5.

#### `data_recebimento`

- Armazena a data em que o dinheiro realmente entrou.
- Enquanto a conta estiver `PENDENTE` ou `ATRASADA`, esse campo ficará vazio (`NULL`).
- Quando a conta passar para `RECEBIDO`, a data será registrada.

#### `id_setor`

- Chave estrangeira (FK).
- Referencia o `id_setor` da tabela `setores`.
- Indica qual setor está relacionado à origem daquele valor.
- Quando o valor for recebido, o dinheiro continua entrando primeiro no Saldo Geral, não diretamente no saldo do setor.

#### `id_categoria`

- Chave estrangeira (FK).
- Referencia o `id_categoria` da tabela `categorias`.
- Indica a categoria daquele valor a receber.
- Deve utilizar uma categoria do tipo `RECEITA`.

#### `id_admin`

- Chave estrangeira (FK).
- Referencia o `id_admin` da tabela `administradores`.
- Permite identificar qual administrador cadastrou a conta a receber.

### Exemplo de venda parcelada

Uma venda de R$ 1.000 foi realizada no cartão de crédito em 5 parcelas de R$ 200.

O sistema poderá criar cinco contas a receber:

```text
Parcela 1 → R$ 200 → vencimento 10/09 → PENDENTE
Parcela 2 → R$ 200 → vencimento 10/10 → PENDENTE
Parcela 3 → R$ 200 → vencimento 10/11 → PENDENTE
Parcela 4 → R$ 200 → vencimento 10/12 → PENDENTE
Parcela 5 → R$ 200 → vencimento 10/01 → PENDENTE
```

Se somente a primeira parcela for recebida:

```text
Parcela 1 → RECEBIDO
Receita criada → R$ 200
Saldo Geral → + R$ 200

Restante a receber → R$ 800
```

### Importante

Uma venda parcelada de R$ 1.000 não significa que o sistema deve adicionar R$ 1.000 imediatamente ao Saldo Geral.

O Saldo Geral aumenta conforme o dinheiro efetivamente entra.

---

## 10 - saldo_geral

### Finalidade

Armazenar quanto dinheiro da empresa está disponível no Saldo Geral naquele momento.

O Saldo Geral representa recursos da empresa que ainda não foram distribuídos para os setores.

### Campos

- `id_saldo_geral`
- `saldo_atual`

### Explicação dos campos

#### `id_saldo_geral`

- Chave primária (PK) da tabela.
- Identifica o registro responsável pelo Saldo Geral.
- Para o My Cash atual, haverá apenas um Saldo Geral para a empresa, então normalmente existirá apenas um registro nessa tabela.

Exemplo:

```text
id_saldo_geral = 1
```

#### `saldo_atual`

- Armazena quanto dinheiro está disponível no Saldo Geral naquele momento.
- O sistema atualiza esse valor automaticamente conforme receitas e distribuições.
- Depois da configuração inicial, o administrador não deve alterar esse campo diretamente para representar operações financeiras.

Exemplo:

```text
Saldo Geral atual = R$ 154.000

Receita recebida de R$ 10.000:
saldo_atual = R$ 164.000

Distribuição de R$ 20.000 para Marketing:
saldo_atual = R$ 144.000
```

### Saldo inicial

- Se a empresa começar a utilizar o My Cash já possuindo dinheiro, esse valor poderá ser informado uma única vez durante a configuração inicial do sistema.
- Esse valor representa o saldo que já existia antes do uso do My Cash.
- Ele não deve ser registrado como uma receita comum, pois o dinheiro não necessariamente entrou depois que o sistema começou a ser utilizado.
- Se nenhum saldo inicial for informado, o Saldo Geral começa em `0`.

Exemplo:

```text
Empresa começa a usar o sistema possuindo R$ 154.000.

Configuração inicial:
saldo_atual = 154000.00
```

Depois disso, as alterações normais devem acontecer pelas operações do sistema.

### O que altera o Saldo Geral

Receita efetivamente recebida:

```text
Saldo Geral aumenta
```

Distribuição para um setor:

```text
Saldo Geral diminui
Saldo do setor aumenta
```

Despesa de um setor:

```text
Saldo Geral não muda
Saldo do setor diminui
```

Realocação entre setores:

```text
Saldo Geral não muda
Saldo do setor origem diminui
Saldo do setor destino aumenta
```

---

# Regras importantes para os desenvolvedores

## 1. Receita não é dinheiro futuro

- Se o dinheiro já entrou → `receitas`.
- Se o dinheiro ainda vai entrar → `contas_receber`.

## 2. Despesa não é conta futura

- Se o dinheiro já saiu → `despesas`.
- Se o dinheiro ainda precisa ser pago → `compromissos`.

## 3. Saldos são controlados pelo sistema

- O administrador não deve editar livremente `setores.saldo_atual` ou `saldo_geral.saldo_atual` para representar uma operação.
- Os valores devem mudar conforme receitas, despesas, distribuições e realocações.

## 4. Operações que alteram dois valores precisam permanecer consistentes

Exemplo de distribuição de R$ 5.000:

```text
Saldo Geral - R$ 5.000
Setor destino + R$ 5.000
```

As duas alterações fazem parte da mesma operação.
Se uma delas falhar, a outra não deve permanecer aplicada sozinha.

O mesmo vale para realocações:

```text
Setor origem - valor
Setor destino + valor
```

Na implementação em MySQL/PHP, isso deverá ser protegido utilizando transações no banco (`START TRANSACTION`, `COMMIT` e `ROLLBACK`).

## 5. Saldo insuficiente

Antes de realizar uma despesa, distribuição ou realocação, o sistema deverá verificar se a origem possui saldo suficiente.

Se não possuir, a operação não deverá ser concluída.

Para despesas, a mensagem definida no projeto é:

```text
Saldo insuficiente. Transação não efetuada.
```

---

# Resumo das tabelas

1. `administradores` → contas dos administradores.
2. `setores` → setores da empresa e saldo atual de cada um.
3. `categorias` → classificação de receitas, despesas, contas a receber e compromissos.
4. `receitas` → dinheiro que realmente entrou.
5. `despesas` → dinheiro que realmente saiu.
6. `compromissos` → dinheiro que ainda precisa ser pago.
7. `transferencias` → distribuições e realocações internas.
8. `movimentacoes` → histórico das operações financeiras.
9. `contas_receber` → dinheiro que ainda precisa ser recebido.
10. `saldo_geral` → valor atualmente disponível e ainda não distribuído aos setores.

---

# Resumo das chaves

## PK - PRIMARY KEY / CHAVE PRIMÁRIA

É o identificador único de um registro dentro da própria tabela.

Exemplo:

```text
setores.id_setor
```

## FK - FOREIGN KEY / CHAVE ESTRANGEIRA

É um campo utilizado para criar uma ligação com outra tabela.

Exemplo:

```text
despesas.id_setor
        ↓
setores.id_setor
```

Isso permite identificar qual setor está relacionado àquela despesa.

---

# Resumo dos IDs utilizados

- `id_admin` → identifica um administrador.
- `id_setor` → identifica um setor.
- `id_categoria` → identifica uma categoria.
- `id_receita` → identifica uma receita.
- `id_despesa` → identifica uma despesa.
- `id_compromisso` → identifica um compromisso financeiro.
- `id_transferencia` → identifica uma transferência.
- `id_movimentacao` → identifica um registro do histórico.
- `id_conta_receber` → identifica um valor que a empresa ainda tem para receber.
- `id_saldo_geral` → identifica o registro do Saldo Geral.
- `id_setor_origem` → identifica o setor de onde saiu dinheiro em uma realocação.
- `id_setor_destino` → identifica o setor que recebeu dinheiro em uma distribuição ou realocação.

---

# Pontos que ainda precisam ser fechados antes do SQL definitivo

- Definir exatamente como `movimentacoes` será ligada aos registros que deram origem ao histórico.
- Definir os tipos SQL e tamanhos dos campos.
- Definir quais campos serão `NOT NULL`, `UNIQUE` e quais poderão receber `NULL`.
- Definir as regras `FOREIGN KEY` e o comportamento ao editar ou excluir registros relacionados.
- Definir como serão tratados estornos/exclusões para que os saldos permaneçam corretos.

Essas decisões devem ser fechadas antes de considerar o script `.sql` definitivo.
