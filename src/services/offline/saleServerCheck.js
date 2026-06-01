import { ApiError } from "../../api/httpClient";
import { salesApi } from "../../api/modules/salesApi";
import { temApiConfigurada } from "../../api";
import { isErroRedeOuIndisponivel } from "./networkError";

export async function vendaJaRegistadaNoServidor(id, { obter = salesApi.obter } = {}) {
  if (!id || !temApiConfigurada()) return false;

  try {
    await obter(id);
    return true;
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 404) return false;
    if (isErroRedeOuIndisponivel(erro)) return false;
    throw erro;
  }
}
