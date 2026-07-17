import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Redirect } from 'expo-router';
import { ApiError } from '@/src/api/httpClient';
import { fetchUsers, type AppUser, type UsersListResponse } from '@/src/api/usersApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import UserEditModal from '@/src/components/UserEditModal';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';
import { homeForClient } from '@/src/utils/clientRoutes';

const PER_PAGE = 10;

const perfis: Record<string, string> = {
  ADMIN: 'Administrador',
  MANAGER: 'Gerente',
  CASHIER: 'Operador',
};

function traduzirPerfil(role?: string) {
  return perfis[role ?? ''] ?? role ?? '—';
}

export default function UsersScreen() {
  const { user: authUser, client } = useAuthStore();
  const [data, setData] = useState<UsersListResponse | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [paginating, setPaginating] = useState(false);
  const [error, setError] = useState('');
  const [utilizadorSelecionado, setUtilizadorSelecionado] = useState<AppUser | null>(null);
  const [modalAberto, setModalAberto] = useState(false);
  const [modoCriacao, setModoCriacao] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      const trimmed = search.trim();
      if (trimmed === searchQuery) return;
      setSearchQuery(trimmed);
      setPage(1);
      setPaginating(true);
    }, 300);

    return () => clearTimeout(timer);
  }, [search, searchQuery]);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const response = await fetchUsers(page, PER_PAGE, searchQuery);
      setData(response);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar utilizadores.');
    } finally {
      setLoading(false);
      setPaginating(false);
    }
  }, [page, searchQuery]);

  useEffect(() => {
    load();
  }, [load]);

  function abrirEdicao(utilizador: AppUser) {
    setModoCriacao(false);
    setUtilizadorSelecionado(utilizador);
    setModalAberto(true);
  }

  function abrirCriacao() {
    setModoCriacao(true);
    setUtilizadorSelecionado(null);
    setModalAberto(true);
  }

  function fecharModal() {
    setModalAberto(false);
    setUtilizadorSelecionado(null);
    setModoCriacao(false);
  }

  function irParaPagina(pagina: number) {
    if (loading || paginating || pagina === page) return;
    setPaginating(true);
    setPage(pagina);
  }

  const items = data?.items ?? [];
  const totalPaginas = data?.meta.last_page ?? 1;

  if (client === 'pos') {
    return <Redirect href={homeForClient(client)} />;
  }

  return (
    <>
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={brand.gold} />}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <View>
            <Text style={styles.section}>
              {data ? `${data.meta.total} utilizador(es)` : 'Utilizadores'}
            </Text>
            <Text style={styles.sectionMeta}>{PER_PAGE} por página</Text>
          </View>
          <Pressable style={styles.newButton} onPress={abrirCriacao}>
            <Text style={styles.newButtonText}>Novo</Text>
          </Pressable>
        </View>

        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Pesquisar por nome, utilizador ou email"
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.searchInput}
        />

        {loading && !data ? <ActivityIndicator color={brand.gold} style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {!loading && items.length === 0 ? (
          <Text style={styles.empty}>Nenhum utilizador encontrado.</Text>
        ) : null}

        {items.map((utilizador) => (
          <Pressable key={utilizador.id} style={styles.card} onPress={() => abrirEdicao(utilizador)}>
            <View style={styles.cardHeader}>
              <Text style={styles.title} numberOfLines={2}>
                {utilizador.name}
              </Text>
              <Text style={[styles.statusBadge, !utilizador.isActive && styles.statusBadgeInactive]}>
                {utilizador.isActive ? 'Activo' : 'Inactivo'}
              </Text>
            </View>
            <Text style={styles.meta}>@{utilizador.username}</Text>
            <Text style={styles.meta}>{utilizador.email}</Text>
            <View style={styles.row}>
              <Text style={styles.role}>{traduzirPerfil(utilizador.role)}</Text>
              <Text style={styles.caixa}>
                {utilizador.caixaAtribuido ||
                  utilizador.registers.map((item) => item.name).join(', ') ||
                  'Sem caixa'}
              </Text>
            </View>
            <Text style={styles.tapHint}>Toque para editar</Text>
          </Pressable>
        ))}

        {data && data.meta.total > 0 ? (
          <View style={styles.pagination}>
            <Pressable
              style={[styles.pageButton, page <= 1 && styles.pageButtonDisabled]}
              disabled={page <= 1 || loading || paginating}
              onPress={() => irParaPagina(Math.max(1, page - 1))}
            >
              <Text style={styles.pageButtonText}>Anterior</Text>
            </Pressable>
            <Text style={styles.pageInfo}>
              Página {page} de {totalPaginas}
            </Text>
            <Pressable
              style={[styles.pageButton, page >= totalPaginas && styles.pageButtonDisabled]}
              disabled={page >= totalPaginas || loading || paginating}
              onPress={() => irParaPagina(Math.min(totalPaginas, page + 1))}
            >
              <Text style={styles.pageButtonText}>Seguinte</Text>
            </Pressable>
          </View>
        ) : null}
      </ScrollView>

      <UserEditModal
        visible={modalAberto}
        user={modoCriacao ? null : utilizadorSelecionado}
        currentUserId={authUser?.id}
        onClose={fecharModal}
        onSaved={() => {
          load();
        }}
      />
      <FullScreenLoader visible={paginating} />
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background },
  header: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
    gap: 12,
  },
  section: { fontWeight: '700', color: brand.dark },
  sectionMeta: { fontSize: 12, color: brand.muted },
  newButton: {
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  newButtonText: { fontWeight: '700', color: brand.dark, fontSize: 13 },
  searchInput: {
    marginHorizontal: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
    backgroundColor: brand.white,
  },
  empty: { paddingHorizontal: 16, color: brand.muted },
  error: { color: brand.danger, padding: 16 },
  card: {
    marginHorizontal: 16,
    marginBottom: 12,
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: brand.border,
    gap: 6,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 8,
  },
  title: { flex: 1, fontWeight: '700', color: brand.dark },
  statusBadge: {
    fontSize: 11,
    fontWeight: '700',
    color: brand.white,
    backgroundColor: '#16a34a',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  statusBadgeInactive: { backgroundColor: brand.muted },
  meta: { color: brand.muted, fontSize: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  role: { color: brand.dark, fontSize: 12, fontWeight: '700' },
  caixa: { color: brand.muted, fontSize: 12, fontWeight: '600', flex: 1, textAlign: 'right' },
  tapHint: { color: brand.muted, fontSize: 11, marginTop: 4 },
  pagination: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 24,
  },
  pageButton: {
    backgroundColor: brand.white,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  pageButtonDisabled: { opacity: 0.45 },
  pageButtonText: { color: brand.dark, fontWeight: '700', fontSize: 13 },
  pageInfo: { color: brand.muted, fontSize: 13, fontWeight: '600' },
});
