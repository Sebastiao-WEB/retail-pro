const atraso = (ms = 350) => new Promise((resolve) => setTimeout(resolve, ms));

const produtosMock = [
  { id: 1, nome: "Pão francês", codigoBarras: "5601000000012", categoria: "Padaria", precoCompra: 6, precoVenda: 8, ivaTipo: "isento", ivaValor: 0, stock: 120 },
  { id: 2, nome: "Leite integral 1L", codigoBarras: "5601000000029", categoria: "Laticínios", precoCompra: 72, precoVenda: 95, ivaTipo: "percentual", ivaValor: 16, stock: 34 },
  { id: 3, nome: "Água mineral 1.5L", codigoBarras: "5601000000036", categoria: "Bebidas", precoCompra: 32, precoVenda: 45, ivaTipo: "percentual", ivaValor: 16, stock: 60 },
  { id: 4, nome: "Cimento 50kg", codigoBarras: "5601000000043", categoria: "Ferragens", precoCompra: 430, precoVenda: 540, ivaTipo: "percentual", ivaValor: 16, stock: 9 },
  { id: 5, nome: "Bolacha Maria", codigoBarras: "5601000000050", categoria: "Mercearia", precoCompra: 54, precoVenda: 70, ivaTipo: "isento", ivaValor: 0, stock: 48 },
  { id: 6, nome: "Refrigerante Cola 2L", codigoBarras: "5601000000067", categoria: "Bebidas", precoCompra: 84, precoVenda: 120, ivaTipo: "percentual", ivaValor: 16, stock: 27 },
  { id: 7, nome: "Açúcar branco 1kg", codigoBarras: "5601000000074", categoria: "Mercearia", precoCompra: 66, precoVenda: 88, ivaTipo: "isento", ivaValor: 0, stock: 14 },
  { id: 8, nome: "Parafuso 8mm (un)", codigoBarras: "5601000000081", categoria: "Ferragens", precoCompra: 3.5, precoVenda: 6, ivaTipo: "monetario", ivaValor: 0.5, stock: 200 },
];

const clientesMock = [
  { id: 1, nome: "Cliente Geral", telefone: "000000000" },
  { id: 2, nome: "Padaria Sol Nascente", telefone: "842001122" },
  { id: 3, nome: "Hotel Mar Azul", telefone: "846778899" },
  { id: 4, nome: "Construtora Nova Era", telefone: "871223344" },
];

const vendasMock = [
  {
    id: 1001,
    cliente: "Padaria Sol Nascente",
    itens: [
      { produtoId: 1, nome: "Pão francês", quantidade: 20, precoVenda: 8, subtotal: 160 },
      { produtoId: 2, nome: "Leite integral 1L", quantidade: 6, precoVenda: 95, subtotal: 570 },
    ],
    total: 730,
    data: "2026-04-23T08:45:00",
    metodoPagamento: "Dinheiro",
  },
  {
    id: 1002,
    cliente: "Hotel Mar Azul",
    itens: [
      { produtoId: 3, nome: "Água mineral 1.5L", quantidade: 12, precoVenda: 45, subtotal: 540 },
      { produtoId: 6, nome: "Refrigerante Cola 2L", quantidade: 5, precoVenda: 120, subtotal: 600 },
    ],
    total: 1140,
    data: "2026-04-23T10:20:00",
    metodoPagamento: "Transferência",
  },
];

const comprasMock = [
  {
    id: 8001,
    fornecedor: "Distribuidora Central",
    itens: [
      { nome: "Leite integral 1L", quantidade: 50, custoUnitario: 70 },
      { nome: "Açúcar branco 1kg", quantidade: 30, custoUnitario: 65 },
    ],
    total: 5450,
    data: "2026-04-22T11:00:00",
  },
];

export async function obterProdutos() {
  await atraso(450);
  return produtosMock.map((produto) => ({ ...produto }));
}

export async function obterClientes() {
  await atraso(300);
  return clientesMock.map((cliente) => ({ ...cliente }));
}

export async function obterVendas() {
  await atraso(500);
  return vendasMock.map((venda) => ({
    ...venda,
    itens: venda.itens.map((item) => ({ ...item })),
  }));
}

export async function obterCompras() {
  await atraso(350);
  return comprasMock.map((compra) => ({
    ...compra,
    itens: compra.itens.map((item) => ({ ...item })),
  }));
}
