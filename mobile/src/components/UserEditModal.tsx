import { useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';
import { ApiError } from '@/src/api/httpClient';
import {
  createUser,
  fetchRegisters,
  fetchStockLocations,
  updateUser,
  type AppUser,
  type RegisterOption,
  type StockLocationOption,
  type UserRole,
} from '@/src/api/usersApi';
import { brand } from '@/src/theme/brand';

type Props = {
  visible: boolean;
  user: AppUser | null;
  currentUserId?: string | null;
  onClose: () => void;
  onSaved: () => void;
};

const perfis: Array<{ key: UserRole; label: string }> = [
  { key: 'ADMIN', label: 'Administrador' },
  { key: 'MANAGER', label: 'Gerente' },
  { key: 'CASHIER', label: 'Operador' },
];

function traduzirPerfil(role?: string) {
  return perfis.find((item) => item.key === role)?.label ?? role ?? '—';
}

export default function UserEditModal({ visible, user, currentUserId, onClose, onSaved }: Props) {
  const isCreate = !user;
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState<UserRole>('CASHIER');
  const [isActive, setIsActive] = useState(true);
  const [registerIds, setRegisterIds] = useState<string[]>([]);
  const [sourceLocationId, setSourceLocationId] = useState<string | null>(null);
  const [registers, setRegisters] = useState<RegisterOption[]>([]);
  const [locations, setLocations] = useState<StockLocationOption[]>([]);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!visible) return;

    Promise.all([fetchRegisters(), fetchStockLocations()])
      .then(([registersData, locationsData]) => {
        setRegisters(registersData);
        setLocations(locationsData);
      })
      .catch(() => {
        setRegisters([]);
        setLocations([]);
      });
  }, [visible]);

  useEffect(() => {
    if (!visible) return;

    if (user) {
      setName(user.name);
      setUsername(user.username);
      setEmail(user.email);
      setPassword('');
      setRole((user.role as UserRole) || 'CASHIER');
      setIsActive(user.isActive);
      setRegisterIds(user.registerIds ?? []);
      setSourceLocationId(user.sourceLocationId);
    } else {
      setName('');
      setUsername('');
      setEmail('');
      setPassword('');
      setRole('CASHIER');
      setIsActive(true);
      setRegisterIds([]);
      setSourceLocationId(null);
    }
    setError('');
  }, [visible, user]);

  function toggleRegister(registerId: string) {
    setRegisterIds((actual) =>
      actual.includes(registerId)
        ? actual.filter((id) => id !== registerId)
        : [...actual, registerId],
    );
  }

  async function handleSave() {
    if (submitting) return;
    setError('');

    if (!name.trim() || !username.trim() || !email.trim()) {
      setError('Preencha nome, utilizador e email.');
      return;
    }

    if (isCreate && password.trim().length < 6) {
      setError('Informe uma senha com pelo menos 6 caracteres.');
      return;
    }

    if (!isCreate && user?.id === currentUserId && !isActive) {
      setError('Não pode desactivar a sua própria conta.');
      return;
    }

    const payload = {
      name: name.trim(),
      username: username.trim(),
      email: email.trim(),
      password: password.trim() || undefined,
      role,
      isActive,
      registerIds,
      sourceLocationId,
    };

    setSubmitting(true);
    try {
      if (isCreate) {
        await createUser({ ...payload, password: password.trim() });
      } else if (user) {
        await updateUser(user.id, payload);
      }

      onSaved();
      Alert.alert('Sucesso', isCreate ? 'Utilizador criado com sucesso.' : 'Utilizador actualizado com sucesso.');
      onClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Não foi possível guardar o utilizador.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>{isCreate ? 'Novo utilizador' : 'Editar utilizador'}</Text>
            <Pressable onPress={onClose} style={styles.closeButton} disabled={submitting}>
              <Text style={styles.closeText}>Fechar</Text>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            <Text style={styles.label}>Nome</Text>
            <TextInput value={name} onChangeText={setName} style={styles.input} editable={!submitting} />

            <Text style={styles.label}>Utilizador</Text>
            <TextInput
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
              autoCorrect={false}
              style={styles.input}
              editable={!submitting}
            />

            <Text style={styles.label}>Email</Text>
            <TextInput
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              style={styles.input}
              editable={!submitting}
            />

            <Text style={styles.label}>{isCreate ? 'Senha' : 'Nova senha (opcional)'}</Text>
            <TextInput
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              placeholder={isCreate ? 'Mínimo 6 caracteres' : 'Deixe vazio para manter'}
              style={styles.input}
              editable={!submitting}
            />

            <Text style={styles.label}>Perfil</Text>
            <View style={styles.chipRow}>
              {perfis.map((item) => (
                <Pressable
                  key={item.key}
                  style={[styles.chip, role === item.key && styles.chipActive]}
                  disabled={submitting}
                  onPress={() => setRole(item.key)}
                >
                  <Text style={[styles.chipText, role === item.key && styles.chipTextActive]}>
                    {item.label}
                  </Text>
                </Pressable>
              ))}
            </View>

            <Text style={styles.label}>Caixas atribuídos</Text>
            <View style={styles.chipRow}>
              {registers.map((item) => (
                <Pressable
                  key={item.id}
                  style={[styles.chip, registerIds.includes(item.id) && styles.chipActive]}
                  disabled={submitting}
                  onPress={() => toggleRegister(item.id)}
                >
                  <Text style={[styles.chipText, registerIds.includes(item.id) && styles.chipTextActive]}>
                    {item.name || item.code}
                  </Text>
                </Pressable>
              ))}
            </View>

            <Text style={styles.label}>Localização de stock</Text>
            <View style={styles.chipRow}>
              <Pressable
                style={[styles.chip, !sourceLocationId && styles.chipActive]}
                disabled={submitting}
                onPress={() => setSourceLocationId(null)}
              >
                <Text style={[styles.chipText, !sourceLocationId && styles.chipTextActive]}>Nenhuma</Text>
              </Pressable>
              {locations.map((item) => (
                <Pressable
                  key={item.id}
                  style={[styles.chip, sourceLocationId === item.id && styles.chipActive]}
                  disabled={submitting}
                  onPress={() => setSourceLocationId(item.id)}
                >
                  <Text style={[styles.chipText, sourceLocationId === item.id && styles.chipTextActive]}>
                    {item.name}
                  </Text>
                </Pressable>
              ))}
            </View>

            <View style={styles.switchRow}>
              <Text style={styles.label}>Utilizador activo</Text>
              <Switch
                value={isActive}
                onValueChange={setIsActive}
                disabled={submitting}
                trackColor={{ false: brand.border, true: brand.gold }}
                thumbColor={brand.white}
              />
            </View>

            {!isCreate && user ? (
              <View style={styles.infoCard}>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Perfil actual: </Text>
                  {traduzirPerfil(user.role)}
                </Text>
                <Text style={styles.infoLine}>
                  <Text style={styles.infoLabel}>Caixa: </Text>
                  {user.caixaAtribuido || user.registers.map((item) => item.name).join(', ') || '—'}
                </Text>
              </View>
            ) : null}

            {error ? <Text style={styles.error}>{error}</Text> : null}

            <Pressable
              style={[styles.saveButton, submitting && styles.saveButtonDisabled]}
              onPress={handleSave}
              disabled={submitting}
            >
              <Text style={styles.saveButtonText}>
                {submitting ? 'A guardar...' : isCreate ? 'Criar utilizador' : 'Guardar alterações'}
              </Text>
            </Pressable>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '92%',
    backgroundColor: brand.white,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingBottom: 24,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: brand.border,
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  title: { fontSize: 16, fontWeight: '700', color: brand.dark },
  closeButton: { paddingHorizontal: 8, paddingVertical: 4 },
  closeText: { color: brand.danger, fontWeight: '700' },
  content: { padding: 16, gap: 8 },
  label: { color: brand.muted, fontSize: 12, fontWeight: '600', marginTop: 4 },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: brand.white,
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
  chip: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: brand.white,
  },
  chipActive: { backgroundColor: brand.dark, borderColor: brand.dark },
  chipText: { color: brand.muted, fontWeight: '600', fontSize: 12 },
  chipTextActive: { color: brand.white },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
  },
  infoCard: {
    backgroundColor: brand.background,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 12,
    padding: 14,
    gap: 6,
    marginTop: 4,
  },
  infoLine: { fontSize: 14, color: brand.dark },
  infoLabel: { fontWeight: '700' },
  error: { color: brand.danger, fontSize: 13, marginTop: 4 },
  saveButton: {
    marginTop: 12,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonDisabled: { opacity: 0.6 },
  saveButtonText: { fontWeight: '700', color: brand.dark, fontSize: 15 },
});
