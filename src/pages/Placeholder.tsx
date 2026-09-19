import { Layout } from '@/components/layout/Layout';

export function PlaceholderPage({ title }: { title: string }) {
  return (
    <Layout>
      <div className="flex items-center justify-center min-h-[50vh]">
        <h1 className="text-3xl font-bold text-text-main">{title} - Em Breve</h1>
      </div>
    </Layout>
  );
}
