import TrackClient from './TrackClient';

export function generateStaticParams() {
  return [
    { id: 'demo' },
    { id: 'default' },
    ...Array.from({ length: 100 }, (_, i) => ({ id: `SEP-${i + 1}` }))
  ];
}

export default function TrackPage() {
  return <TrackClient />;
}
