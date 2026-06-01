export const UNIDADE_VENDA_KG = "KG";
export const UNIDADE_VENDA_UN = "UN";

export function normalizarUnidadeVenda(valor) {
  return String(valor || "").toUpperCase() === UNIDADE_VENDA_KG ? UNIDADE_VENDA_KG : UNIDADE_VENDA_UN;
}

export function vendidoPorPeso(produto) {
  return normalizarUnidadeVenda(produto?.unidadeVenda) === UNIDADE_VENDA_KG;
}

export function passoQuantidade(unidadeVenda) {
  return normalizarUnidadeVenda(unidadeVenda) === UNIDADE_VENDA_KG ? 0.001 : 1;
}

export function quantidadeMinima(unidadeVenda) {
  return normalizarUnidadeVenda(unidadeVenda) === UNIDADE_VENDA_KG ? 0.001 : 1;
}

/**
 * Normaliza quantidade para venda (kg com 3 casas ou unidades inteiras).
 * @returns {number|null}
 */
export function normalizarQuantidadeVenda(quantidade, unidadeVenda) {
  const numero = Number(quantidade);
  if (!Number.isFinite(numero)) return null;

  if (normalizarUnidadeVenda(unidadeVenda) === UNIDADE_VENDA_KG) {
    const kg = Math.round(numero * 1000) / 1000;
    return kg > 0 ? kg : null;
  }

  const unidades = Math.floor(numero);
  return unidades >= 1 ? unidades : null;
}

export function gramasParaKg(gramas) {
  const gramasNumero = Number(gramas);
  if (!Number.isFinite(gramasNumero) || gramasNumero <= 0) return null;
  return normalizarQuantidadeVenda(gramasNumero / 1000, UNIDADE_VENDA_KG);
}

export function parseQuantidadeTexto(texto, unidadeVenda) {
  const limpo = String(texto ?? "")
    .trim()
    .replace(/\s/g, "")
    .replace(",", ".");
  if (limpo === "") return null;
  const numero = Number(limpo);
  if (!Number.isFinite(numero)) return null;
  return normalizarQuantidadeVenda(numero, unidadeVenda);
}

export function formatarQuantidadeExibicao(quantidade, unidadeVenda, locale = "pt-MZ") {
  if (normalizarUnidadeVenda(unidadeVenda) === UNIDADE_VENDA_KG) {
    const kg = Number(quantidade);
    if (!Number.isFinite(kg) || kg <= 0) return "0 g";
    if (kg < 1) {
      return `${Math.round(kg * 1000)} g`;
    }
    return `${kg.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 })} kg`;
  }
  return String(Math.floor(Number(quantidade) || 0));
}

export function formatarStockExibicao(stock, unidadeVenda, locale = "pt-MZ") {
  if (normalizarUnidadeVenda(unidadeVenda) === UNIDADE_VENDA_KG) {
    return formatarQuantidadeExibicao(stock, UNIDADE_VENDA_KG, locale);
  }
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(Math.floor(Number(stock) || 0));
}
