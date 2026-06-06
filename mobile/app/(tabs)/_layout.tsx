import { SymbolView } from 'expo-symbols';
import { Tabs, Redirect } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';
import type { AuthUser } from '@/src/types/auth';
import { brand } from '@/src/theme/brand';

function temRoleGestao(user: AuthUser | null) {
  const roles = user?.roles ?? (user?.role ? [user.role] : []);
  return roles.some((role) => role === 'ADMIN' || role === 'MANAGER');
}

function podeGerirUtilizadores(user: AuthUser | null) {
  if (!user) return false;
  if (user.permissions?.includes('users.manage')) return true;
  return temRoleGestao(user);
}

function podeGerirStock(user: AuthUser | null) {
  if (!user) return false;
  const permissoesStock = [
    'stock.reload',
    'stock.movements.view',
    'stock.transfers.view',
    'stock.transfers.manage',
    'stock_locations.view',
  ];
  if (user.permissions?.some((item) => permissoesStock.includes(item))) return true;
  return temRoleGestao(user);
}

function podeVerFechosCaixa(user: AuthUser | null) {
  if (!user) return false;
  if (user.permissions?.includes('cash_sessions.view')) return true;
  return temRoleGestao(user);
}

export default function TabLayout() {
  const { user } = useAuthStore();
  const mostrarUtilizadores = podeGerirUtilizadores(user);
  const mostrarStock = podeGerirStock(user);
  const mostrarFechos = podeVerFechosCaixa(user);

  if (!user) {
    return <Redirect href="/login" />;
  }

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: brand.gold,
        tabBarInactiveTintColor: brand.muted,
        headerStyle: { backgroundColor: brand.dark },
        headerTintColor: '#fff',
        tabBarStyle: { backgroundColor: brand.white },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'chart.bar', android: 'bar_chart', web: 'bar_chart' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="reversals"
        options={{
          title: 'Reversões',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'arrow.uturn.left', android: 'undo', web: 'undo' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="sales"
        options={{
          title: 'Vendas',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'cart', android: 'shopping_cart', web: 'shopping_cart' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="cash-closings"
        options={{
          title: 'Fechos',
          href: mostrarFechos ? undefined : null,
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'lock.rectangle', android: 'lock', web: 'lock' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="products"
        options={{
          title: 'Produtos',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'shippingbox', android: 'inventory_2', web: 'inventory_2' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="stock"
        options={{
          title: 'Stock',
          href: mostrarStock ? undefined : null,
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'archivebox', android: 'inventory', web: 'inventory' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="users"
        options={{
          title: 'Utilizadores',
          href: mostrarUtilizadores ? undefined : null,
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'person.2', android: 'group', web: 'group' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'Conta',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'person.crop.circle', android: 'person', web: 'person' }} tintColor={color} size={24} />
          ),
        }}
      />
    </Tabs>
  );
}
