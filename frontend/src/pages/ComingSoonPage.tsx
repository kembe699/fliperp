import { Construction } from 'lucide-react'

import { PageHeader } from '@/components/layout/PageHeader'

export function ComingSoonPage({ title, parent }: { title: string; parent?: string }) {
  return (
    <div>
      <PageHeader parent={parent} title={title} />
      <div className="flex flex-col items-center justify-center rounded-xl border border-border bg-card px-6 py-24 text-center">
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-accent">
          <Construction className="h-7 w-7 text-primary" strokeWidth={1.5} />
        </div>
        <p className="text-lg font-semibold text-foreground">Coming soon</p>
        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
          The {title} module is on the way. This page already renders inside the full app shell so navigation won't
          break.
        </p>
      </div>
    </div>
  )
}
