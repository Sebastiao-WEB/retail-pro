/** Normaliza texto para pesquisa (sem acentos, minúsculas). */
export function normalizarTextoPesquisa(texto) {
  return String(texto || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

/** Código de barras sem espaços. */
export function normalizarCodigoBarras(codigo) {
  return String(codigo || "")
    .replace(/\s+/g, "")
    .trim();
}

/** Termo típico de leitor (EAN/UPC). */
export function pareceCodigoBarras(termo) {
  const limpo = normalizarCodigoBarras(termo);
  return /^\d{8,14}$/.test(limpo);
}

function pontuarProduto(produto, consulta, codigoConsulta) {
  const nomeNorm = normalizarTextoPesquisa(produto?.nome);
  const codigoNorm = normalizarCodigoBarras(produto?.codigoBarras);
  const codigoNormTexto = normalizarTextoPesquisa(produto?.codigoBarras);

  if (codigoConsulta && codigoNorm && codigoNorm === codigoConsulta) return 100;
  if (codigoConsulta && codigoNorm && codigoNorm.endsWith(codigoConsulta)) return 85;
  if (consulta && nomeNorm && nomeNorm === consulta) return 90;
  if (consulta && nomeNorm && nomeNorm.startsWith(consulta)) return 75;
  if (consulta && nomeNorm && nomeNorm.includes(consulta)) return 55;
  if (consulta && codigoNormTexto && codigoNormTexto.includes(consulta)) return 45;
  if (consulta && codigoNorm && codigoNorm.includes(codigoConsulta || consulta)) return 40;

  return 0;
}

/**
 * Pesquisa no catálogo local completo (sem limite artificial).
 * Ordena por relevância: código exacto > nome exacto > prefixo > contém.
 */
export function pesquisarProdutosNoCatalogo(produtos, termo, mapear = (item) => item) {
  const consulta = normalizarTextoPesquisa(termo);
  const codigoConsulta = normalizarCodigoBarras(termo);
  if (!consulta && !codigoConsulta) return [];

  const vistos = new Set();
  const resultados = [];

  for (const produto of produtos || []) {
    if (!produto?.id || vistos.has(produto.id)) continue;
    const score = pontuarProduto(produto, consulta, codigoConsulta);
    if (score <= 0) continue;
    vistos.add(produto.id);
    resultados.push({ score, produto: mapear(produto) });
  }

  return resultados
    .sort(
      (a, b) =>
        b.score - a.score ||
        String(a.produto?.nome || "").localeCompare(String(b.produto?.nome || ""), "pt")
    )
    .map((item) => item.produto);
}

export function resolverProdutoPorCodigoBarras(produtos, indiceCodigos, codigo, mapear = (item) => item) {
  const normalizado = normalizarCodigoBarras(codigo);
  if (!normalizado) return null;

  const doIndice = indiceCodigos?.[normalizado];
  if (doIndice) return mapear(doIndice);

  const encontrado = (produtos || []).find(
    (produto) => normalizarCodigoBarras(produto?.codigoBarras) === normalizado
  );
  return encontrado ? mapear(encontrado) : null;
}
