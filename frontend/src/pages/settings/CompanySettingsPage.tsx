import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Building2, Upload, X } from 'lucide-react'

import { deleteCompanyLogo, fetchCompany, updateCompany, uploadCompanyLogo } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'

export function CompanySettingsPage() {
  const queryClient = useQueryClient()
  const authCompany = useAuthStore((state) => state.company)
  const setAuthCompany = useAuthStore((state) => state.setCompany)
  const { can } = usePermissions()

  const { data: company, isLoading, isError } = useQuery({
    queryKey: ['company', authCompany?.id],
    queryFn: () => fetchCompany(authCompany!.id),
    enabled: !!authCompany?.id,
  })

  const [name, setName] = useState('')
  const [currencyCode, setCurrencyCode] = useState('')
  const [timezone, setTimezone] = useState('')
  const [logoUrl, setLogoUrl] = useState('')

  useEffect(() => {
    if (!company) return
    setName(company.name)
    setCurrencyCode(company.currency_code ?? '')
    setTimezone(company.timezone ?? '')
    setLogoUrl(company.logo_url ?? '')
  }, [company])

  const saveMutation = useMutation({
    mutationFn: () =>
      updateCompany(authCompany!.id, {
        name,
        currency_code: currencyCode || null,
        timezone: timezone || null,
        logo_url: logoUrl || null,
      }),
    onSuccess: (updated) => {
      toast.success('Company details saved')
      if (authCompany) setAuthCompany({ ...authCompany, ...updated })
      queryClient.invalidateQueries({ queryKey: ['company', authCompany?.id] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canEdit = can('companies.update')

  const fileInputRef = useRef<HTMLInputElement>(null)
  const [logoPreview, setLogoPreview] = useState<string | null>(null)

  const uploadLogoMutation = useMutation({
    mutationFn: (file: File) => uploadCompanyLogo(file),
    onSuccess: (updated) => {
      toast.success('Company logo uploaded')
      setLogoUrl(updated.logo_url ?? '')
      setLogoPreview(null)
      if (authCompany) setAuthCompany({ ...authCompany, ...updated })
      queryClient.invalidateQueries({ queryKey: ['company', authCompany?.id] })
    },
    onError: (error) => {
      toast.error(getApiErrorInfo(error).message)
      setLogoPreview(null)
    },
  })

  const deleteLogoMutation = useMutation({
    mutationFn: () => deleteCompanyLogo(),
    onSuccess: (updated) => {
      toast.success('Company logo removed')
      setLogoUrl(updated.logo_url ?? '')
      if (authCompany) setAuthCompany({ ...authCompany, ...updated })
      queryClient.invalidateQueries({ queryKey: ['company', authCompany?.id] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleLogoFileSelect = (file: File | null) => {
    if (!file) return
    setLogoPreview(URL.createObjectURL(file))
    uploadLogoMutation.mutate(file)
  }

  return (
    <div>
      <PageHeader parent="Settings" title="Company" />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
          You don't have permission to view company settings, or the company could not be loaded.
        </p>
      ) : isLoading || !company ? (
        <p className="p-6 text-sm text-muted-foreground">Loading…</p>
      ) : (
        <Card>
          <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <div className="space-y-2 sm:col-span-2">
              <Label>Company Logo</Label>
              <div className="flex items-start gap-4">
                <Avatar className="h-20 w-20 rounded-lg border border-border">
                  {(logoPreview ?? logoUrl) && (
                    <AvatarImage src={logoPreview ?? logoUrl} alt={company.name} className="object-contain" />
                  )}
                  <AvatarFallback className="rounded-lg bg-muted">
                    <Building2 className="h-1/2 w-1/2 text-muted-foreground" />
                  </AvatarFallback>
                </Avatar>
                {canEdit && (
                  <div className="flex-1 space-y-2">
                    <div className="flex items-center gap-2">
                      <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(event) => handleLogoFileSelect(event.target.files?.[0] ?? null)}
                      />
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={uploadLogoMutation.isPending}
                        onClick={() => fileInputRef.current?.click()}
                      >
                        <Upload className="h-4 w-4" />
                        {uploadLogoMutation.isPending ? 'Uploading…' : 'Upload Logo'}
                      </Button>
                      {logoUrl && (
                        <button
                          type="button"
                          onClick={() => deleteLogoMutation.mutate()}
                          disabled={deleteLogoMutation.isPending}
                          className="flex items-center gap-1 text-xs text-muted-foreground hover:text-destructive"
                        >
                          <X className="h-3 w-3" />
                          Remove Logo
                        </button>
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground">JPG, PNG or WEBP, max 2MB. Used on invoices, quotations, statements and receipts.</p>
                  </div>
                )}
              </div>
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Company Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} disabled={!canEdit} />
            </div>
            <div className="space-y-1.5">
              <Label>Currency Code</Label>
              <Input value={currencyCode} onChange={(event) => setCurrencyCode(event.target.value.toUpperCase())} maxLength={3} disabled={!canEdit} />
            </div>
            <div className="space-y-1.5">
              <Label>Timezone</Label>
              <Input value={timezone} onChange={(event) => setTimezone(event.target.value)} placeholder="UTC" disabled={!canEdit} />
            </div>
            {canEdit && (
              <div className="sm:col-span-2">
                <Button disabled={!name || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                  Save Changes
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
