// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   Text,
// } from '@shopify/ui-extensions-react/admin';

// // The target used here must match the target used in the extension's toml file (./shopify.extension.toml)
// const TARGET = 'admin.product-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   // The useApi hook provides access to several useful APIs like i18n and data.
//   const {i18n, data} = useApi(TARGET);
//   console.log({data});

//   return (
//     // The AdminBlock component provides an API for setting the title of the Block extension wrapper.
//     <AdminBlock title="My Block Extension">
//       <BlockStack>
//         <Text fontWeight="bold">{i18n.translate('welcome', {target: TARGET})}</Text>
//       </BlockStack>
//     </AdminBlock>
//   );
// }

import {
  reactExtension,
  useApi,
  AdminBlock,
  BlockStack,
  Text,
  InlineStack,
} from '@shopify/ui-extensions-react/admin';
import { useEffect, useState } from 'react';
 
const TARGET = 'admin.order-details.block.render';
 
export default reactExtension(TARGET, () => <App />);
 
function App() {
  const { data } = useApi(TARGET);

  // GID and numeric order ID
  const orderGID = data?.selected?.[0]?.id;
  console.log('🔍 orderGID:', orderGID);
 
  const orderId = orderGID?.split('/')?.pop();
  console.log('🔍 orderId:', orderId);
 
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
 
  if (window.location.hostname === "localhost") {
    BASE_URL = "http://localhost:5000/api";
  } else if (window.location.hostname === "rightangled.24livehost.com") {
    BASE_URL = "https://rightangled.24livehost.com/api";
  } else {
    // Default to production
    BASE_URL = "https://yoursite.com/api";
  }

  useEffect(() => {
    if (!orderId) {
      console.warn('⛔ No orderId found. Skipping fetch.');
      return;
    }
 
    const fetchLogs = async () => {
      setLoading(true);
      console.log('📡 Fetching logs for orderId:', orderId);
 
      try {
        const response = await fetch(
           `${BASE_URL}/prescriber/audit-logs/order`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId }),
          }
        );
 
        console.log('🌐 Response status:', response.status);
 
        const result = await response.json();
        console.log('✅ API response:', result);
 
        setLogs(result);
      } catch (error) {
        console.error('❌ Error fetching audit logs:', error);
        setLogs([]);
      } finally {
        console.log('🔁 Finished fetching logs');
        setLoading(false);
      }
    };
 
    fetchLogs();
  }, [orderId]);
 
  return (
    <AdminBlock title="Prescriber Audit Logs">
      <BlockStack spacing="loose">
        {loading ? (
          <Text>Loading logs...</Text>
        ) : logs.length === 0 ? (
          <Text>No Prescriber logs found for this order.</Text>
        ) : (
          logs.map((log) => {
            console.log('📝 Rendering log:', log);
            return (
              <BlockStack key={log.id} spacing="tight">
                <InlineStack>
                  <Text>🕓 {new Date(log.created_at).toLocaleString()}</Text>
                </InlineStack>
                <Text tone="subdued">{log.details}</Text>
                <Text size="small" tone="secondary">Action: {log.action}</Text>
              </BlockStack>
            );
          })
        )}
      </BlockStack>
    </AdminBlock>
  );
}