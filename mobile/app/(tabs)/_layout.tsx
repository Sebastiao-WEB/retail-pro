import { useMemo, useState } from 'react';
import { Pressable, StyleSheet } from 'react-native';
import { SymbolView } from 'expo-symbols';
import { Tabs, Redirect } from 'expo-router';
import AdminDrawer, { type DrawerItem } from '@/src/components/AdminDrawer';
import HeaderUserMenu from '@/src/components/HeaderUserMenu';
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

function HeaderLeftMenu({ onPress }: { onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={styles.menuTrigger}
      accessibilityRole="button"
      accessibilityLabel="Abrir menu"
    >
      <SymbolView
        name={{ ios: 'line.3.horizontal', android: 'menu', web: 'menu' }}
        tintColor="#fff"
        size={24}
      />
    </Pressable>
  );
}

export default function TabLayout() {
  const { user, client } = useAuthStore();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const modoPos = client === 'pos';
  const mostrarUtilizadores = !modoPos && podeGerirUtilizadores(user);
  const mostrarStock = !modoPos && podeGerirStock(user);
  const mostrarFechos = !modoPos && podeVerFechosCaixa(user);
  const mostrarGestao = !modoPos;

  const drawerItems = useMemo(() => {
    const items: DrawerItem[] = [
      {
        key: 'reversals',
        title: 'Reversões',
        href: '/reversals',
        icon: { ios: 'arrow.uturn.left', android: 'undo', web: 'undo' },
      },
    ];

    if (mostrarFechos) {
      items.push({
        key: 'cash-closings',
        title: 'Fechos de caixa',
        href: '/cash-closings',
        icon: { ios: 'lock.rectangle', android: 'lock', web: 'lock' },
      });
    }

    if (mostrarStock) {
      items.push({
        key: 'stock',
        title: 'Stock',
        href: '/stock',
        icon: { ios: 'archivebox', android: 'inventory', web: 'inventory' },
      });
    }

    if (mostrarUtilizadores) {
      items.push({
        key: 'users',
        title: 'Utilizadores',
        href: '/users',
        icon: { ios: 'person.2', android: 'group', web: 'group' },
      });
    }

    return items;
  }, [mostrarFechos, mostrarStock, mostrarUtilizadores]);

  if (!user) {
    return <Redirect href="/login" />;
  }

  return (
    <>
      <Tabs
        screenOptions={{
          tabBarActiveTintColor: brand.gold,
          tabBarInactiveTintColor: brand.muted,
          headerStyle: { backgroundColor: brand.dark },
          headerTintColor: '#fff',
          headerLeft: mostrarGestao
            ? () => <HeaderLeftMenu onPress={() => setDrawerOpen(true)} />
            : undefined,
          headerLeftContainerStyle: mostrarGestao ? { paddingLeft: 12 } : undefined,
          headerRight: () => <HeaderUserMenu />,
          headerRightContainerStyle: { paddingRight: 12 },
          tabBarStyle: { backgroundColor: brand.white },
        }}
      >
        <Tabs.Screen
          name="pos"
          options={{
            title: 'POS',
            href: modoPos ? undefined : null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'barcode.viewfinder', android: 'qr_code_scanner', web: 'qr_code_scanner' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="index"
          options={{
            title: 'Dashboard',
            href: mostrarGestao ? undefined : null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'chart.bar', android: 'bar_chart', web: 'bar_chart' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="sales"
          options={{
            title: modoPos ? 'Histórico' : 'Vendas',
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'cart', android: 'shopping_cart', web: 'shopping_cart' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="products"
          options={{
            title: 'Produtos',
            href: mostrarGestao ? undefined : null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'shippingbox', android: 'inventory_2', web: 'inventory_2' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="more"
          options={{
            title: 'Conta',
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'person.crop.circle', android: 'person', web: 'person' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="reversals"
          options={{
            title: 'Reversões',
            // POS: tab visível. Admin: só no drawer.
            href: modoPos ? undefined : null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'arrow.uturn.left', android: 'undo', web: 'undo' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="cash-closings"
          options={{
            title: 'Fechos',
            href: null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'lock.rectangle', android: 'lock', web: 'lock' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="stock"
          options={{
            title: 'Stock',
            href: null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'archivebox', android: 'inventory', web: 'inventory' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="users"
          options={{
            title: 'Utilizadores',
            href: null,
            tabBarIcon: ({ color }) => (
              <SymbolView
                name={{ ios: 'person.2', android: 'group', web: 'group' }}
                tintColor={color}
                size={24}
              />
            ),
          }}
        />
      </Tabs>

      {mostrarGestao ? (
        <AdminDrawer
          visible={drawerOpen}
          items={drawerItems}
          userName={user.name || user.username || undefined}
          onClose={() => setDrawerOpen(false)}
        />
      ) : null}
    </>
  );
}

const styles = StyleSheet.create({
  menuTrigger: {
    padding: 4,
  },
});
