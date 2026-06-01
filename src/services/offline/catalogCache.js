const PREFIXO = "retailpro:offline:catalog:";

function chaveCatalogo(filtros = {}) {
  const local = filtros.source_location_id || filtros.location_id || "global";
  return `${PREFIXO}${local}`;
}

export function salvarCatalogoOffline(filtros, produtos) {
  if (!Array.isArray(produtos) || !produtos.length) return;
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(
    chaveCatalogo(filtros),
    JSON.stringify({
      atualizadoEm: new Date().toISOString(),
      produtos,
    })
  );
}

export function carregarCatalogoOffline(filtros = {}) {
  if (typeof localStorage === "undefined") return null;
  try {
    const raw = localStorage.getItem(chaveCatalogo(filtros));
    if (!raw) return null;
    const dados = JSON.parse(raw);
    if (!Array.isArray(dados?.produtos)) return null;
    return dados;
  } catch {
    return null;
  }
}

export function temCatalogoOffline(filtros = {}) {
  const dados = carregarCatalogoOffline(filtros);
  return Array.isArray(dados?.produtos) && dados.produtos.length > 0;
}

export function limparCatalogosOffline() {
  if (typeof localStorage === "undefined") return;
  const chaves = [];
  for (let i = 0; i < localStorage.length; i += 1) {
    const chave = localStorage.key(i);
    if (chave?.startsWith(PREFIXO)) chaves.push(chave);
  }
  chaves.forEach((chave) => localStorage.removeItem(chave));
}
