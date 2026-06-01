export function somarUnidadesVenda(venda) {
  const itens = Array.isArray(venda?.itens) ? venda.itens : Array.isArray(venda?.items) ? venda.items : [];
  return itens.reduce((acc, item) => acc + Math.max(0, Number(item?.quantidade || 0)), 0);
}

export function extrairVendaApi(resposta) {
  if (!resposta || typeof resposta !== "object") return null;
  if (resposta.data && typeof resposta.data === "object" && !Array.isArray(resposta.data)) {
    return resposta.data;
  }
  return resposta;
}
