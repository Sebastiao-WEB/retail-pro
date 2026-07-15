import { Alert } from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';

export function useConfirmLogout() {
  const logout = useAuthStore((state) => state.logout);

  return () => {
    Alert.alert('Terminar sessão', 'Tem a certeza que deseja sair?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Sair',
        style: 'destructive',
        onPress: async () => {
          await logout();
          router.replace('/login');
        },
      },
    ]);
  };
}
