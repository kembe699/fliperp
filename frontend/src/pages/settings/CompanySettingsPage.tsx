import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Building2, Coins, Upload, X } from 'lucide-react'

import { deleteCompanyLogo, fetchCompany, fetchCurrencies, updateCompany, uploadCompanyLogo } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function CompanySettingsPage() {
  const queryClient = useQueryClient()
  const authCompany = useAuthStore((state) => state.company)
  const setAuthCompany = useAuthStore((state) => state.setCompany)
  const { can } = usePermissions()
  const canEdit = can('companies.update')

  const { data: company, isLoading, isError } = useQuery({
    queryKey: ['company', authCompany?.id],
    queryFn: () => fetchCompany(authCompany!.id),
    enabled: !!authCompany?.id,
  })

  const { data: currencies } = useQuery({
    queryKey: ['currencies'],
    queryFn: fetchCurrencies,
  })

  const [name, setName] = useState('')
  const [timezone, setTimezone] = useState('')
  const [logoUrl, setLogoUrl] = useState('')

  useEffect(() => {
    if (!company) return
    setName(company.name)
    setTimezone(company.timezone ?? '')
    setLogoUrl(company.logo_url ?? '')
  }, [company])

  const onCompanySaved = (updated: typeof company) => {
    if (!updated) return
    if (authCompany) setAuthCompany({ ...authCompany, ...updated })
    queryClient.invalidateQueries({ queryKey: ['company', authCompany?.id] })
  }

  const saveMutation = useMutation({
    mutationFn: () =>
      updateCompany(authCompany!.id, {
        name,
        timezone: timezone || null,
        logo_url: logoUrl || null,
      }),
    onSuccess: (updated) => {
      toast.success('Company details saved')
      onCompanySaved(updated)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const [currencyCode, setCurrencyCode] = useState('')

  useEffect(() => {
    if (company) setCurrencyCode(company.currency_code ?? '')
  }, [company])

  const currencySaveMutation = useMutation({
    mutationFn: () => updateCompany(authCompany!.id, { currency_code: currencyCode || null }),
    onSuccess: (updated) => {
      toast.success('Currency updated — it now applies across the app')
      onCompanySaved(updated)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const selectedCurrency = currencies?.find((currency) => currency.code === currencyCode)

  const fileInputRef = useRef<HTMLInputElement>(null)
  const [logoPreview, setLogoPreview] = useState<string | null>(null)

  const uploadLogoMutation = useMutation({
    mutationFn: (file: File) => uploadCompanyLogo(file),
    onSuccess: (updated) => {
      toast.success('Company logo uploaded')
      setLogoUrl(updated.logo_url ?? '')
      setLogoPreview(null)
      onCompanySaved(updated)
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
      onCompanySaved(updated)
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
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <Card className="lg:col-span-2">
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
              <div className="space-y-1.5 sm:col-span-2">
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

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Coins className="h-4 w-4" />
                Currency Settings
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-xl border border-border bg-muted/40 p-4 text-center">
                <p className="text-3xl font-bold text-foreground">{selectedCurrency?.symbol ?? currencyCode ?? '—'}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {selectedCurrency ? `${selectedCurrency.name} (${selectedCurrency.code})` : 'No currency selected'}
                </p>
              </div>

              <div className="space-y-1.5">
                <Label>Currency</Label>
                <Select value={currencyCode || undefined} onValueChange={setCurrencyCode} disabled={!canEdit}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select a currency" />
                  </SelectTrigger>
                  <SelectContent>
                    {(currencies ?? []).map((currency) => (
                      <SelectItem key={currency.code} value={currency.code}>
                        {currency.name} ({currency.code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">Used for every amount shown across the app — POS, invoices, reports and PDFs.</p>
              </div>

              {canEdit && (
                <Button
                  disabled={!currencyCode || currencyCode === (company.currency_code ?? '') || currencySaveMutation.isPending}
                  onClick={() => currencySaveMutation.mutate()}
                >
                  Save Currency
                </Button>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
