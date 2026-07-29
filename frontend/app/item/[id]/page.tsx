import ItemClient from './ItemClient';

export function generateStaticParams() {
  return [{ id: '1' }];
}

export default function ItemPage() {
  return <ItemClient />;
}
