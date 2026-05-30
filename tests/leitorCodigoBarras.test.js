import { describe, expect, it } from "vitest";
import {
  criarEstadoLeitorCodigoBarras,
  processarTeclaLeitorCodigoBarras,
} from "../src/utils/leitorCodigoBarras";

function tecla(valor, extras = {}) {
  return { key: valor, ctrlKey: false, metaKey: false, altKey: false, ...extras };
}

describe("leitorCodigoBarras", () => {
  it("detecta leitura rapida, reinicia o codigo e confirma com Enter", () => {
    let estado = criarEstadoLeitorCodigoBarras();
    let agora = 1_000;

    ({ estado } = processarTeclaLeitorCodigoBarras(estado, tecla("8"), agora));
    agora += 15;
    const inicio = processarTeclaLeitorCodigoBarras(estado, tecla("4"), agora);
    expect(inicio.acao).toBe("iniciar-leitor");
    expect(inicio.valor).toBe("84");

    estado = inicio.estado;
    agora += 15;
    const continuar = processarTeclaLeitorCodigoBarras(estado, tecla("1"), agora);
    expect(continuar.acao).toBe("continuar-leitor");

    agora += 15;
    const confirmar = processarTeclaLeitorCodigoBarras(continuar.estado, tecla("Enter"), agora);
    expect(confirmar.acao).toBe("confirmar-leitor");
  });

  it("mantem digitacao manual lenta como entrada normal", () => {
    let estado = criarEstadoLeitorCodigoBarras();
    let agora = 500;

    ({ estado } = processarTeclaLeitorCodigoBarras(estado, tecla("c"), agora));
    agora += 200;
    const segunda = processarTeclaLeitorCodigoBarras(estado, tecla("o"), agora);
    expect(segunda.acao).toBe("normal");

    agora += 200;
    const enter = processarTeclaLeitorCodigoBarras(segunda.estado, tecla("Enter"), agora);
    expect(enter.acao).toBe("confirmar-manual");
  });
});
